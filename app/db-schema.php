<?php

$schema = new Doctrine\DBAL\Schema\Schema();

// Issues
$issuesTable = $schema->createTable('zenhub_issues');
$issuesTable->addColumn('id', 'integer', ['unsigned' => true]);
$issuesTable->addColumn('estimate', 'float', ['notnull' => false]);
$issuesTable->setPrimaryKey(['id']);
$issuesTable->addIndex(['estimate']);
$issuesTable->addForeignKeyConstraint('github_issues', ['id'], ['id']);

return $schema;
