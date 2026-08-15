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
        foreach(['wpnb_test_source','wpnb_fetch_source','wpnb_fetch_all_sources','wpnb_toggle_source','wpnb_delete_source','wpnb_bulk_sources','wpnb_skip_item'] as$action)$this->assertStringContainsString($action,$source);
        foreach(['Test Before Saving','Fetch News','Fetch from All Active Sources','Edit','Activate','Deactivate','Delete','wpnb-sources-table','wpnb-pool-table'] as$text)$this->assertStringContainsString($text,$source);
        $this->assertStringContainsString("REQUEST_METHOD",$source);
        $this->assertStringContainsString("!=='POST'",$source);
        $this->assertStringContainsString('check_admin_referer',$source);
        $this->assertStringContainsString('Security::canManage()',$source);
        $this->assertStringContainsString("requirePostAction('wpnb_fetch_source_",$source);
        $this->assertStringContainsString("requirePostAction('wpnb_fetch_all_sources')",$source);
        $this->assertStringContainsString("requirePostAction('wpnb_repair_database')",$source);
        $this->assertStringContainsString("'wpnb-health'],true)?'manage_options'",$source);
        $this->assertStringContainsString('confirm(',$source);
    }

    public function testSchemaAndMigrationEnforceCanonicalUniquenessAndMoveRelations(): void
    {
        $database=file_get_contents(dirname(__DIR__).'/includes/Database.php');
        $schema=file_get_contents(dirname(__DIR__).'/includes/DatabaseSchema.php');
        $repair=file_get_contents(dirname(__DIR__).'/includes/DatabaseRepair.php');
        $migration=file_get_contents(dirname(__DIR__).'/includes/SourceMigration.php');
        $this->assertStringContainsString("'canonical_hash'=>\"char(64)",$schema);
        $this->assertStringContainsString('canonical_hash_unique',$migration);
        $this->assertStringContainsString('START TRANSACTION',$migration);
        $this->assertStringContainsString('COMMIT',$migration);
        $this->assertStringContainsString('ROLLBACK',$migration);
        $this->assertStringContainsString('moveRelations($jobsTable',$migration);
        $this->assertStringContainsString('moveRelations($generationsTable',$migration);
        $this->assertStringContainsString('source_duplicate_migration',$migration);
        $this->assertStringNotContainsString('wp_posts',$migration);
        $this->assertStringContainsString("update_option('wpnb_schema_version',WPNB_SCHEMA_VERSION",$repair);
        $this->assertStringContainsString("\$after['status']==='healthy'",$repair);
        $this->assertStringNotContainsString('DROP TABLE',$repair);
        $this->assertStringNotContainsString('TRUNCATE',$repair);
        $this->assertStringContainsString('migration_journal',$repair);
        $this->assertStringContainsString('schema_fingerprint',$repair);
    }

    public function testInactiveSourcesAreExcludedFromCronAndImporter(): void
    {
        $plugin=file_get_contents(dirname(__DIR__).'/includes/Plugin.php');
        $importer=file_get_contents(dirname(__DIR__).'/includes/SourceImporter.php');
        $this->assertStringContainsString('WHERE active=1',$plugin);
        $this->assertStringContainsString("!(int)\$source['active']",$importer);
    }

    public function testManualRunBypassesOnlyTheScheduleInterval():void
    {
        $admin=file_get_contents(dirname(__DIR__).'/admin/Admin.php');
        $plugin=file_get_contents(dirname(__DIR__).'/includes/Plugin.php');
        $this->assertStringContainsString('new ManualImportService()',$admin);
        $this->assertStringContainsString('importActiveSources(bool $force=false)',$plugin);
        $this->assertStringContainsString('Support::utcTimestamp',$plugin);
        $this->assertStringContainsString('if(!$force&&$last!==null',$plugin);
        $this->assertStringContainsString("do_action('wpnb_sources_polled')",$plugin);
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

    public function testPoolBulkAndDraftSourcePrivacyControlsArePresent():void
    {
        $admin=file_get_contents(dirname(__DIR__).'/admin/Admin.php');$draft=file_get_contents(dirname(__DIR__).'/includes/DraftService.php');$bulk=file_get_contents(dirname(__DIR__).'/includes/PoolBulkService.php');$maintenance=file_get_contents(dirname(__DIR__).'/includes/DraftMaintenanceService.php');
        foreach(['wpnb_bulk_pool','selection_scope','filteredPoolIds','Create AI posts','Add to queue','Delete from pool','Processed/Published News','Publish Previously Created Drafts']as$value)$this->assertStringContainsString($value,$admin);
        foreach(['wpnb_pool_bulk_lock_','successful','skipped','failed',"['new','review','error']"]as$value)$this->assertStringContainsString($value,$bulk);
        foreach(['_wpnb_source_id','_wpnb_source_url','_wpnb_feed_item_id','_wpnb_content_hash','_wpnb_ai_provider','_wpnb_ai_model','_wpnb_generated_at']as$key)$this->assertStringContainsString($key,$draft);
        $this->assertStringNotContainsString("<strong>'.esc_html__('Source:",$draft);
        $this->assertStringContainsString("'post_status'=>'draft'",$maintenance);$this->assertStringContainsString("get_post_status(\$id)!=='draft'",$maintenance);$this->assertStringContainsString('confirm_cleanup',$admin);
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
        $this->assertStringContainsString('DatabaseRepair($wpdb)',$admin);
        $this->assertStringContainsString('wpnb_repair_database',$admin);
        $this->assertStringNotContainsString("update_option('wpnb_schema_version',WPNB_SCHEMA_VERSION",$admin);
        foreach(['url_invalid','host_invalid','dns_failed','ip_blocked','redirect_blocked','http_failed','http_status_invalid','content_type_invalid','body_empty','xml_invalid','feed_invalid','database_schema_invalid','database_failed']as$code)$this->assertStringContainsString($code,file_get_contents(dirname(__DIR__).'/includes/SourceConnectionTester.php').file_get_contents(dirname(__DIR__).'/includes/SourceService.php'));
        $this->assertStringContainsString('result_code',$admin);
        $this->assertStringContainsString('test_id',$admin);
        $this->assertStringContainsString('Copy Diagnostic Information',$admin);
        $this->assertStringNotContainsString('$wpdb->last_query',$admin);
    }

    public function testEngineRepairIsConfirmedAllowlistedJournaledAndNeverDestructive():void
    {
        $admin=file_get_contents(dirname(__DIR__).'/admin/Admin.php');$engine=file_get_contents(dirname(__DIR__).'/includes/DatabaseEngineRepair.php');
        $this->assertStringContainsString('confirm_engine_conversion',$admin);$this->assertStringContainsString('required>',$admin);$this->assertStringContainsString("requirePostAction('wpnb_repair_database')",$admin);
        $this->assertStringContainsString("'db_unknown'",$admin);$this->assertStringContainsString("'engine_conversion_required'",$admin);
        foreach(['migration_journal','sources','feed_items','jobs','ai_generations','logs','daily_usage']as$table)$this->assertStringContainsString("'$table'",$engine);
        foreach(['innodb_unavailable','alter_permission_denied','engine_conversion_required','engine_conversion_failed','engine_conversion_verified']as$code)$this->assertStringContainsString($code,$engine.$admin);
        $this->assertStringContainsString('SHOW ENGINES',$engine);$this->assertStringContainsString('SHOW GRANTS FOR CURRENT_USER',$engine);$this->assertStringContainsString('CHECKSUM TABLE',$engine);$this->assertStringContainsString('ENGINE=InnoDB',$engine);$this->assertStringContainsString("update_option(self::STATE_OPTION,\$state,false)",$engine);
        $this->assertStringNotContainsString('DROP TABLE',$engine);$this->assertStringNotContainsString('TRUNCATE',$engine);$this->assertStringNotContainsString('wp_posts',$engine);$this->assertStringNotContainsString('$this->db->query($_',$engine);
    }
}
