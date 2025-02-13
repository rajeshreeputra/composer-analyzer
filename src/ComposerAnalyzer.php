<?php

namespace ComposerAnalyzer;

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ComposerAnalyzer extends Command {

  private $openaiApiKey;

  protected static $defaultName = 'analyzer:start';

  public function __construct() {
    parent::__construct();
    $this->openaiApiKey = getenv('OPENAI_API_KEY'); // Set your OpenAI API key in environment variables.
  }

  protected function configure() {
    $this->setDescription('Analyze composer.json and composer.lock files to explain package update issues.')
      ->addArgument('composer_json', InputArgument::REQUIRED, 'Path to composer.json file')
      ->addArgument('composer_lock', InputArgument::REQUIRED, 'Path to composer.lock file');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $composerJsonPath = $input->getArgument('composer_json');
    $composerLockPath = $input->getArgument('composer_lock');

    if (!file_exists($composerJsonPath)) {
      $output->writeln('<error>composer.json file not found.</error>');
      return Command::FAILURE;
    }

    if (!file_exists($composerLockPath)) {
      $output->writeln('<error>composer.lock file not found.</error>');
      return Command::FAILURE;
    }

    $composerJson = file_get_contents($composerJsonPath);
    $composerLock = file_get_contents($composerLockPath);

    $analysisResult = $this->analyze_files($composerJson, $composerLock);
    $output->writeln("<info>Analysis Result:</info>\n" . $analysisResult);

    return Command::SUCCESS;
  }

  private function analyze_files($composerJson, $composerLock) {
    $client = new Client();

    // Truncate the input data to fit within the request size limits
    $maxLength = 1000; // Adjust this value as needed
    $composerJson = substr($composerJson, 0, $maxLength);
    $composerLock = substr($composerLock, 0, $maxLength);

    $prompt = "Analyze the following composer.json and composer.lock files and explain why a specific package cannot be updated:\n\n";
    $prompt .= "composer.json:\n" . $composerJson . "\n\n";
    $prompt .= "composer.lock:\n" . $composerLock . "\n\n";
    $prompt .= "Provide a detailed explanation and suggest possible solutions.";

    try {
      $response = $client->post('https://api.openai.com/v1/completions', [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->openaiApiKey,
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'model' => 'gpt-3.5-turbo-instruct', // Use the appropriate GPT model.
          'prompt' => $prompt,
          'max_tokens' => 500,
          'temperature' => 0.7,
        ],
      ]);

      $responseData = json_decode($response->getBody(), true);
      return $responseData['choices'][0]['text'] ?? 'No response from AI.';
    } catch (Exception $e) {
      return 'Error analyzing files: ' . $e->getMessage();
    }
  }

  private function analyze_files1($composerJson, $composerLock) {
    $client = new Client();

    $prompt = "Analyze the following composer.json and composer.lock files and explain why a specific package cannot be updated:\n\n";
    $prompt .= "composer.json:\n" . $composerJson . "\n\n";
    $prompt .= "composer.lock:\n" . $composerLock . "\n\n";
    $prompt .= "Provide a detailed explanation and suggest possible solutions.";

    try {
      $response = $client->post('https://api.openai.com/v1/completions', [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->openaiApiKey,
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'model' => 'text-embedding-ada-002', // Use the appropriate GPT model.
          'prompt' => $prompt,
          'max_tokens' => 500,
          'temperature' => 0.7,
        ],
      ]);

      $responseData = json_decode($response->getBody(), true);
      return $responseData['choices'][0]['text'] ?? 'No response from AI.';
    } catch (Exception $e) {
      return 'Error analyzing files: ' . $e->getMessage();
    }
  }
}