<?php

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Silly\Application;
use Symfony\Component\Console\Output\OutputInterface;

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env file if it exists
if (is_file(getcwd() . '/.env')) {
    $dotenv = new Dotenv(getcwd());
    $dotenv->load();
}

$app = new Application;
$app->setDefaultCommand('intro');

// DB
$dbConfig = new \Doctrine\DBAL\Configuration();
$dbConfig->setFilterSchemaAssetsExpression('/^zenhub_/');
$db = \Doctrine\DBAL\DriverManager::getConnection([
    'dbname' => getenv('DB_NAME'),
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: 3306,
    'driver' => 'pdo_mysql',
    'charset' => 'UTF8',
], $dbConfig);

$app->command('intro', function (OutputInterface $output) {
    $output->writeln('<comment>Getting started</comment>');
    $output->writeln('- copy the <info>.env.dist</info> file to <info>.env</info> and set up the required configuration parameters.');
    $output->writeln('- run <info>db-init</info> to setup the database (by default no SQL command will actually be run so that you can check them).');
    $output->writeln('- run <info>sync [user/repository]</info> to synchronize GitHub data with the database.');
    $output->writeln('The first time you use this command you will want to run <info>sync [user/repository] --since-forever</info> to synchronize everything.');
    $output->writeln('Then you can (for example) setup a cron to run every hour without the <info>--since-forever</info> flag.');
})->descriptions('Displays an introduction on how to use this application.');

$app->command('sync repository', function ($repository, OutputInterface $output) use ($db) {
    $http = new Client();

    // Get repository ID from GitHub
    $response = $http->request('GET', "https://api.github.com/repos/$repository", [
        'headers' => [
            'Authorization' => 'token ' . getenv('GITHUB_TOKEN'),
        ],
    ]);
    $repositoryInfo = json_decode((string) $response->getBody(), true);
    $repositoryId = $repositoryInfo['id'];

    // Fetch data for all issues found in the database that are not already processed
    // If you need to refresh all issues, truncate the zenhub_issues table
    $statement = $db->query('SELECT * FROM github_issues WHERE id NOT IN (SELECT id FROM zenhub_issues)');
    while ($issue = $statement->fetch()) {
        $issueId = $issue['id'];
        $output->writeln(sprintf('Synchronizing issue <info>#%d</info>', $issueId));

        $response = $http->request('GET', "https://api.zenhub.io/p1/repositories/$repositoryId/issues/$issueId", [
            'headers' => [
                'X-Authentication-Token' => getenv('ZENHUB_TOKEN'),
            ],
        ]);
        $zenhubData = json_decode((string) $response->getBody(), true);

        $sql = <<<MYSQL
INSERT INTO zenhub_issues (id, estimate)
    VALUES (:id, :estimate)
    ON DUPLICATE KEY UPDATE
        id=:id,
        estimate=:estimate
MYSQL;
        $db->executeQuery($sql, [
            'id' => $issueId,
            'estimate' => isset($zenhubData['estimate']['value']) ? floatval($zenhubData['estimate']['value']) : null,
        ]);
    }
});

$app->command('db-init [--force]', function ($force, OutputInterface $output) use ($db) {
    $targetSchema = require __DIR__ . '/db-schema.php';
    $currentSchema = $db->getSchemaManager()->createSchema();

    $migrationQueries = $currentSchema->getMigrateToSql($targetSchema, $db->getDatabasePlatform());

    $db->transactional(function () use ($migrationQueries, $force, $output, $db) {
        foreach ($migrationQueries as $query) {
            $output->writeln(sprintf('Running <info>%s</info>', $query));
            if ($force) {
                $db->exec($query);
            }
        }
        if (empty($migrationQueries)) {
            $output->writeln('<info>The database is up to date</info>');
        }
    });

    if (!$force) {
        $output->writeln('<comment>No query was run, use the --force option to run the queries</comment>');
    } else {
        $output->writeln('<comment>Queries were successfully run against the database</comment>');
    }
});

$app->run();
