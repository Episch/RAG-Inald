<?php

namespace App\Tests\Command;

use App\Command\ProcessRequirementsCommand;
use App\Service\RequirementsExtractionService;
use App\Dto\Requirements\RequirementsGraphDto;
use App\Dto\Requirements\RequirementDto;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Integration Tests für ProcessRequirementsCommand
 */
class ProcessRequirementsCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private string $testFile;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $this->testFile = sys_get_temp_dir() . '/test_requirements.txt';
        file_put_contents($this->testFile, 'Test requirements content');

        $command = $application->find('app:process-requirements');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            @unlink($this->testFile);
        }
    }

    public function testExecuteWithNonExistentFile(): void
    {
        // Act
        $this->commandTester->execute([
            'path' => '/non/existent/file.pdf'
        ]);

        // Assert
        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('existiert nicht', $output);
    }

    public function testExecuteDisplaysHelp(): void
    {
        // Act
        $this->commandTester->execute([
            '--help' => true
        ]);

        // Assert
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Requirements', $output);
        $this->assertStringContainsString('IRREB', $output);
        $this->assertStringContainsString('Neo4j', $output);
    }

    public function testExecuteWithNoImportOption(): void
    {
        // Skip if file operations would fail
        $this->markTestSkipped('Requires full integration setup');
    }
}

