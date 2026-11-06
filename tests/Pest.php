<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature/WebhookProtocolTest.php', 'Feature/AlertsTest.php', 'Feature/NetworkExperimentTest.php');
