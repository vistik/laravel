<?php namespace BackupManager\Laravel;

use BackupManager\Databases;
use BackupManager\Filesystems;
use BackupManager\Compressors;
use Symfony\Component\Process\Process;
use Illuminate\Support\ServiceProvider;
use BackupManager\Config\Config;
use BackupManager\ShellProcessing\ShellProcessor;

/**
 * Class BackupManagerServiceProvider
 * @package BackupManager\Integrations\Laravel
 */
class Laravel5BackupManagerServiceProvider extends ServiceProvider {

    protected $defer = true;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot() {
        $configPath = base_path('config');
        $this->publishes([$configPath . "/backup-manager.php" => config_path('backup-manager.php')], 'backup-manager');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {

        $configPath = base_path('config');
        $this->mergeConfigFrom($configPath . '/backup-manager.php', 'backup-manager');
        $this->registerFilesystemProvider();
        $this->registerDatabaseProvider();
        $this->registerCompressorProvider();
        $this->registerShellProcessor();
        $this->registerArtisanCommands();
    }

    /**
     * Register the filesystem provider.
     *
     * @return void
     */
    private function registerFilesystemProvider() {
        $this->app->bind('BackupManager\Filesystems\FilesystemProvider', function ($app) {
            $provider = new Filesystems\FilesystemProvider(new Config($app['config']['backup-manager']));
            $provider->add(new Filesystems\Awss3Filesystem);
            $provider->add(new Filesystems\DropboxFilesystem);
            $provider->add(new Filesystems\FtpFilesystem);
            $provider->add(new Filesystems\LocalFilesystem);
            $provider->add(new Filesystems\RackspaceFilesystem);
            $provider->add(new Filesystems\SftpFilesystem);
            return $provider;
        });
    }

    /**
     * Register the database provider.
     *
     * @return void
     */
    private function registerDatabaseProvider() {
        $this->app->bind('BackupManager\Databases\DatabaseProvider', function ($app) {
            $provider = new Databases\DatabaseProvider($this->getDatabaseConfig($app['config']['database.connections']));
            $provider->add(new Databases\MysqlDatabase);
            $provider->add(new Databases\PostgresqlDatabase);
            return $provider;
        });
    }

    /**
     * Register the compressor provider.
     *
     * @return void
     */
    private function registerCompressorProvider() {
        $this->app->bind('BackupManager\Compressors\CompressorProvider', function () {
            $provider = new Compressors\CompressorProvider;
            $provider->add(new Compressors\GzipCompressor);
            $provider->add(new Compressors\NullCompressor);
            return $provider;
        });
    }

    /**
     * Register the filesystem provider.
     *
     * @return void
     */
    private function registerShellProcessor() {
        $this->app->bind('BackupManager\ShellProcessing\ShellProcessor', function () {
            return new ShellProcessor(new Process(''));
        });
    }

    /**
     * Register the artisan commands.
     *
     * @return void
     */
    private function registerArtisanCommands() {
        $this->commands([
            'BackupManager\Laravel\DbBackupCommand',
            'BackupManager\Laravel\DbRestoreCommand',
            'BackupManager\Laravel\DbListCommand',
        ]);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides() {
        return [
            'BackupManager\Filesystems\FilesystemProvider',
            'BackupManager\Databases\DatabaseProvider',
            'BackupManager\ShellProcessing\ShellProcessor',
        ];
    }

    private function getDatabaseConfig($connections) {
        $mapped = array_map(function ($connection) {
            if ( ! in_array($connection['driver'], ['mysql', 'pgsql'])) {
                return;
            }

            if (isset($connection['port'])) {
                $port = $connection['port'];
            } else {
                if ($connection['driver'] == 'mysql') {
                    $port = '3306';
                } elseif ($connection['driver'] == 'pgsql') {
                    $port = '5432';
                }
            }

            return [
                'type'     => $connection['driver'],
                'host'     => $connection['host'],
                'port'     => $port,
                'user'     => $connection['username'],
                'pass'     => $connection['password'],
                'database' => $connection['database'],
            ];
        }, $connections);
        return new Config($mapped);
    }
}
