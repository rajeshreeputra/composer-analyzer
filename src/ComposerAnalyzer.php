<?php

namespace Rajeshreeputra\ComposerAnalyzer;

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class ComposerAnalyzer extends Command {

  private $openaiApiKey;

  protected static $defaultName = 'analyzer:start';

  public function __construct() {
    parent::__construct();
    $this->openaiApiKey = getenv('OPENAI_API_KEY'); // Set your OpenAI API key in environment variables.
  }

  protected function configure() {
    $this->setDescription('Analyze composer.json and composer.lock files to explain package update issues.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $helper = $this->getHelper('question');

    $output->writeln('<info>Welcome to the Composer Analyzer CLI. Type "bye" to exit.</info>');

    while (true) {
      // Ask if the user wants to update a specific package or all packages.
      $updateChoice = new ChoiceQuestion(
        'Do you want to update a specific package or all packages? (type "bye" to exit)',
        ['specific', 'all', 'bye'],
        0
      );
      $updateChoice->setErrorMessage('Invalid choice.');
      $updateType = $helper->ask($input, $output, $updateChoice);

      if ($updateType === 'bye') {
        $output->writeln('<info>Goodbye!</info>');
        break;
      }

      $packageName = null;
      if ($updateType === 'specific') {
        // Ask for the package name.
        $packageQuestion = new Question('Enter the package name you want to update (type "bye" to exit): ');
        $packageName = $helper->ask($input, $output, $packageQuestion);

        if (strtolower($packageName) === 'bye') {
          $output->writeln('<info>Goodbye!</info>');
          break;
        }
      }

      // Ask for confirmation before proceeding.
      $confirmation = new ConfirmationQuestion(
        'Do you want to proceed with the update? (yes/no) ',
        false
      );
      if (!$helper->ask($input, $output, $confirmation)) {
        $output->writeln('<info>Update canceled.</info>');
        continue;
      }

      // Attempt to update the package(s).
      $output->writeln('<info>Attempting to update the package(s)...</info>');
      $updateCommand = $updateType === 'specific' ? "composer require $packageName" : 'composer update';
      exec($updateCommand, $updateOutput, $returnCode);

      if ($returnCode === 0) {
        $output->writeln('<info>Package(s) updated successfully.</info>');
        continue;
      }

      // If the update fails, analyze the issue.
      $output->writeln('<error>Failed to update the package(s). Analyzing the issue...</error>');

      $composerJson = file_get_contents('composer.json');
      $composerLock = file_get_contents('composer.lock');

      if (!$composerJson || !$composerLock) {
        $output->writeln('<error>composer.json or composer.lock file not found.</error>');
        continue;
      }

      $analysisResult = $this->analyze_files($composerJson, $composerLock, $packageName);
      $output->writeln("<info>Analysis Result:</info>\n" . $analysisResult);
    }

    return Command::SUCCESS;
  }

  private function analyze_files($composerJson, $composerLock, $packageName = null) {
    $client = new Client();

    $prompt = "Analyze the following composer.json and composer.lock files and explain why ";
    $prompt .= $packageName ? "the package '$packageName' cannot be updated" : "packages cannot be updated";
    $prompt .= ":\n\n";
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