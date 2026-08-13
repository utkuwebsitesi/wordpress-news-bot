<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;
use PHPUnit\Framework\TestCase; use WordPressNewsBot\SetupState;
final class SetupStateTest extends TestCase { public function testInitialStateNeedsSetup():void{$this->assertTrue(SetupState::needsSetup(SetupState::initial()));} public function testSkippedStateDoesNotPrompt():void{$this->assertFalse(SetupState::needsSetup(SetupState::skipped()));} public function testCompletedStateDoesNotPrompt():void{$state=SetupState::completed();$this->assertFalse(SetupState::needsSetup($state));$this->assertSame(5,$state['step']);} }
