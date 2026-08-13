<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;

final class SourceMaintenanceSafetyTest extends TestCase
{
    public function testPluginAndPackageMetadataUseUtkuwebAsAuthor():void
    {
        $plugin=file_get_contents(dirname(__DIR__).'/wordpress-news-bot.php');
        $this->assertStringContainsString('Plugin Name: WordPress News Bot',$plugin);
        $this->assertStringContainsString('Author: Utkuweb',$plugin);
        $this->assertStringNotContainsString('Author URI:',$plugin);
        $composer=json_decode((string)file_get_contents(dirname(__DIR__).'/composer.json'),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame('Utkuweb',$composer['authors'][0]['name']??null);
    }

    public function testAdminProvidesProtectedPostOnlyCrudBulkAndMobileActions(): void
    {
        $source=file_get_contents(dirname(__DIR__).'/admin/Admin.php');
        foreach(['wpnb_test_source','wpnb_toggle_source','wpnb_delete_source','wpnb_bulk_sources'] as$action)$this->assertStringContainsString($action,$source);
        foreach(['Test Before Saving','Edit','Activate','Deactivate','Delete','wpnb-sources-table'] as$text)$this->assertStringContainsString($text,$source);
        $this->assertStringContainsString("REQUEST_METHOD",$source);
        $this->assertStringContainsString("!=='POST'",$source);
        $this->assertStringContainsString('check_admin_referer',$source);
        $this->assertStringContainsString('Security::canManage()',$source);
        $this->assertStringContainsString('confirm(',$source);
    }

    public function testSchemaAndMigrationEnforceCanonicalUniquenessAndMoveRelations(): void
    {
        $database=file_get_contents(dirname(__DIR__).'/includes/Database.php');
        $migration=file_get_contents(dirname(__DIR__).'/includes/SourceMigration.php');
        $this->assertStringContainsString('canonical_hash char(64)',$database);
        $this->assertStringContainsString('canonical_hash_unique',$migration);
        $this->assertStringContainsString('START TRANSACTION',$migration);
        $this->assertStringContainsString('COMMIT',$migration);
        $this->assertStringContainsString('ROLLBACK',$migration);
        $this->assertStringContainsString('moveRelations($jobsTable',$migration);
        $this->assertStringContainsString('moveRelations($generationsTable',$migration);
        $this->assertStringContainsString('source_duplicate_migration',$migration);
        $this->assertStringNotContainsString('wp_posts',$migration);
        $this->assertStringContainsString("update_option('wpnb_schema_version', WPNB_SCHEMA_VERSION",$database);
        $this->assertStringContainsString('catch (SourceRecoveryRequired',$database);
        $this->assertLessThan(strpos($database,"update_option('wpnb_schema_version'"),strpos($database,'->run($previousVersion)'));
    }

    public function testInactiveSourcesAreExcludedFromCronAndImporter(): void
    {
        $plugin=file_get_contents(dirname(__DIR__).'/includes/Plugin.php');
        $importer=file_get_contents(dirname(__DIR__).'/includes/SourceImporter.php');
        $this->assertStringContainsString('WHERE active=1',$plugin);
        $this->assertStringContainsString("!(int)\$source['active']",$importer);
    }

    public function testAdminDoesNotRenderRawSourceExceptions(): void
    {
        $admin=file_get_contents(dirname(__DIR__).'/admin/Admin.php');
        $this->assertStringNotContainsString('storeAdminNotice($e->getMessage())',$admin);
        $this->assertStringNotContainsString("'last_error'=>sanitize_text_field(\$e->getMessage())",$admin);
        $this->assertStringContainsString("__('The previous source migration did not complete.",$admin);
        $plugin=file_get_contents(dirname(__DIR__).'/includes/Plugin.php');
        $this->assertStringNotContainsString("'last_error'=>sanitize_text_field(\$e->getMessage())",$plugin);
    }

    public function testFailedFormStateAndRecoveryAcknowledgementAreImplemented():void
    {
        $admin=file_get_contents(dirname(__DIR__).'/admin/Admin.php');
        $this->assertStringContainsString('retainSourceForm($input)',$admin);
        $this->assertStringContainsString("set_transient(\$this->sourceFormKey()",$admin);
        $this->assertStringContainsString('source_test_token',$admin);
        $this->assertStringContainsString('verifiedSourceTest($input)',$admin);
        $this->assertStringContainsString('wpnb_dismiss_recovery',$admin);
        $this->assertStringContainsString('update_user_meta',$admin);
        $this->assertStringContainsString("delete_option('wpnb_source_recovery_required')",$admin);
        $this->assertStringContainsString('Database::activate()',$admin);
        $this->assertStringNotContainsString("update_option('wpnb_schema_version',WPNB_SCHEMA_VERSION",$admin);
        foreach(['url_invalid','host_invalid','dns_failed','ip_blocked','redirect_blocked','http_failed','http_status_invalid','content_type_invalid','body_empty','xml_invalid','feed_invalid','database_failed']as$code)$this->assertStringContainsString($code,file_get_contents(dirname(__DIR__).'/includes/SourceConnectionTester.php').file_get_contents(dirname(__DIR__).'/includes/SourceService.php'));
        $this->assertStringContainsString('result_code',$admin);
        $this->assertStringContainsString('test_id',$admin);
    }
}
