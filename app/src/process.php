<?php
use Rajentrivedi\TokenizerX\TokenizerX;

require __DIR__ . '/vendor/autoload.php';

try {
    $conn = new PDO('pgsql:host=postgres;port=5432;dbname='.$_ENV["POSTGRES_DB"] .'',''.$_ENV["POSTGRES_USER"] .'',''.$_ENV["POSTGRES_PASSWORD"] .'');
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

#get prompt from POST or CLI or create default "2+2="
$prompt = $_POST['prompt'] ?? null;
if (! $prompt) {
    $prompt = $argv[0] ?? null;
}
if (! $prompt) {
    $prompt = 'What is the result of 2 + 2?';
}

const CONTEXT_TOKEN_COUNT = 1000;
$apiKey = file_get_contents('api_key.txt');

$client = OpenAI::Client($apiKey);
$model = 'gpt-4o';

#load documents
$path    = 'documents';
$files = scandir($path);
$files = array_diff(scandir($path), array('.', '..'));
$documents = [];
foreach($files as $file) {
    $document = file_get_contents($path . '/' . $file);
    $documents[] = $document;

    #load documents to postgres database
    $statement = $conn->prepare("INSERT INTO document(text, embedding) VALUES(:doc, :embed)");
    $resp = $client->embeddings()->create([
        'input' => $document,
        'model' => 'text-embedding-ada-002'
    ]);
    $statement->execute([
        "doc" => $document,
        "embed" => json_encode($resp->embeddings[0]->embedding)
    ]);
}

# get embeddings for prompt
$respPrompt = $client->embeddings()->create([
    'input' => $prompt,
    'model' => 'text-embedding-ada-002'
]);
$embeddingPrompt =json_encode($resp->embeddings[0]->embedding);

#find documents with similarity to prompt
$documentsChosen = $conn->query("SELECT text from document order by embedding <-> '" . $embeddingPrompt . "'  desc limit 2;")
    ->fetchAll();

# prepare RAG input
$contextTokenCount = CONTEXT_TOKEN_COUNT - TokenizerX::count($prompt) - 20;
$input = '';
foreach($documentsChosen as $document) {
    $input .= $document['text'] . "\n";
    $tokens = TokenizerX::tokens($input, "gpt-4");

    if (count($tokens) > $contextTokenCount) {
        break;
    }
}

# prepare API input
$input .= "\n\n##### INPUT: \n"  . $prompt . "\n##### RESPONSE:\n";

# get API response
$response = $client->chat()->create([
    'model' => $model,
    'messages' => [
        [
            'content' => $input,
            'role' => 'user'
        ]
    ]
]);
echo "<h1>DOCUMENTS:</h1>";
echo "<br /><br />";
echo $input;
echo "<br /><br /><br /><br />";
echo "<h1>RESPONSE:</h1>";
echo "<br /><br />";
echo $response->choices[0]->message->content;