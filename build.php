#!/usr/bin/env php
<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\suggest;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/helpers.php';

/**
 * Constants
 */
const STUBS_DIR = 'stubs';
const GITHUB_DIR = '.github';
const CONFIG_DIR = 'config';
const TESTS_DIR = 'tests';
const SRC_DIR = 'src';

/**
 * Entrypoint
 */
function main(): void
{
    $details = promptForPackageDetails();
    $features = promptForFeatures();

    $details = array_merge($details, $features);

    checkAndSetGitRemote($details['packageName']);

    scaffoldDirectories();

    scaffoldConfigFile($details['packageName']);

    updateLicense($details['packageAuthorName']);

    scaffoldGithubFiles($features['enabledFeatures']);

    scaffoldFacadeAndProvider($details);

    scaffoldTests($details);

    scaffoldFeatureFiles($features, $details);

    addComposerData(buildComposerData($details, $features));

    installDependencies($details);

    cleanupInstaller();

    cleanupStubs();

    info(message: "Installation complete! You're all set to start building your package.");
}

/**
 * Prompt user for package details.
 */
function promptForPackageDetails(): array
{
    $packageAuthorName = suggest(
        label: "What is the package author's name?",
        options: fn () => [run(command: 'git config user.name')],
        required: true,
    );

    $email = suggest(
        label: "What is the package author's email?",
        options: fn () => [run(command: 'git config user.email')],
        required: true,
        validate: fn ($email) => match (true) {
            ! filter_var($email, FILTER_VALIDATE_EMAIL) => 'The email must be a valid email address',
            default => null,
        },
    );

    $username = slugify(text(
        label: 'What is your VCS username?',
        required: true,
        hint: 'This will be your VCS provider username.',
    ));

    $vendorName = ucwords(string: suggest(
        label: 'What namespace should the package use?',
        options: fn () => [str_replace(search: '-', replace: '', subject: ucwords($username))],
        placeholder: 'Consider: '.ucwords($username),
        required: true,
        validate: fn ($vendor) => match (true) {
            ! preg_match(pattern: '/^[A-Za-z0-9\-]+$/i', subject: $vendor) => 'Vendor namespace must be alphanumeric',
            ! preg_match(pattern: '/^[A-Z]/i', subject: $vendor) => 'Vendor namespace must be capitalized',
            default => null,
        },
    ));

    $vendorNameSlug = slugify($vendorName);

    $packageName = slugify(text: suggest(
        label: 'What name would you like to give your package?',
        options: [basename(getcwd())],
        required: true,
        validate: fn ($value) => match (true) {
            ! preg_match(pattern: '/^[A-Za-z0-9\-\s]+$/i', subject: $value) => 'Package name must be alphanumeric',
            default => null,
        },
    ));

    $packageDescription = text(
        label: 'Describe what your package tries to accomplish',
        required: true,
    );

    $className = ucwords(string: suggest(
        label: 'Choose a class name for your package',
        options: fn () => [
            str_replace(search: ' ', replace: '', subject: ucwords(string: str_replace(['-', '_'], replace: ' ',
                subject: $packageName))),
        ],
        required: true,
        validate: fn ($value) => match (true) {
            ! preg_match(pattern: '/^[A-Za-z0-9\-]+$/i', subject: $value) => 'Class name must be alphanumeric',
            default => null,
        },
    ));

    $phpVersion = select(
        label: 'What is the minimum PHP version your package supports?',
        options: ['8.4', '8.3'],
        default: '8.4',
    );

    $laravelVersion = select(
        label: 'What is the minimum Laravel version your package supports?',
        options: ['12', '11'],
        default: '12',
    );

    $testingFramework = select(
        label: 'Select a Testing Framework',
        options: ['Pest', 'PHPUnit'],
        default: 'Pest',
    );

    return compact(
        'packageAuthorName',
        'email',
        'username',
        'vendorName',
        'vendorNameSlug',
        'packageName',
        'packageDescription',
        'className',
        'phpVersion',
        'laravelVersion',
        'testingFramework'
    );
}

/**
 * Prompt for features and extras.
 */
function promptForFeatures(): array
{
    $enabledFeatures = multiselect(
        label: 'Which extra features do you want enabled?',
        options: ['Dependabot', 'Update CHANGELOG', 'Pint', 'PHPStan', 'Rector'],
        default: ['Dependabot', 'Update CHANGELOG'],
    );

    $enableLarastan = null;

    if (in_array(needle: 'PHPStan', haystack: $enabledFeatures)) {
        $enableLarastan = confirm(label: 'Do you want to enable Larastan?');
    }

    return compact('enabledFeatures', 'enableLarastan');
}

/**
 * Check and set git remote if needed.
 */
function checkAndSetGitRemote(string $packageName): void
{
    $gitRemoteOrigin = run(command: 'git config --get remote.origin.url');

    if (str_contains(haystack: $gitRemoteOrigin, needle: 'skeleton.git')) {
        $parts = explode(separator: '/', string: $gitRemoteOrigin);
        $parts[count($parts) - 1] = $packageName.'.git';
        $gitRemoteOrigin = implode(separator: '/', array: $parts);
        run(command: "git remote set-url origin $gitRemoteOrigin");
    }
}

/**
 * Scaffold directories.
 */
function scaffoldDirectories(): void
{
    $directories = [
        'config',
        'database' => ['factories', 'migrations', 'seeders'],
        'resources' => ['views'],
        'routes',
        'src' => ['Facades'],
        'tests',
    ];

    foreach ($directories as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $subDir) {
                $dirPath = $key.'/'.$subDir;

                if (! file_exists($dirPath)) {
                    mkdir($dirPath, recursive: true);
                }
            }
        } elseif (! file_exists($value)) {
            mkdir($value, recursive: true);
        }
    }
}

/**
 * Scaffold the config file.
 */
function scaffoldConfigFile(string $packageName): void
{
    $configPath = CONFIG_DIR."/$packageName.php";

    touch($configPath);

    file_put_contents($configPath, data: "<?php\n\nreturn [\n\n];\n");
}

/**
 * Update LICENSE file.
 */
function updateLicense(string $author): void
{
    replaceInFile([':year', ':fullName'], [date(format: 'Y'), $author], filename: 'LICENSE');
}

/**
 * Scaffold GitHub files.
 */
function scaffoldGithubFiles(array $enabledFeatures): void
{
    if (! is_dir(filename: GITHUB_DIR)) {
        mkdir(directory: GITHUB_DIR);
        mkdir(directory: GITHUB_DIR.'/workflows');
        mkdir(directory: GITHUB_DIR.'/ISSUE_TEMPLATE');
    }

    if (in_array('Dependabot', $enabledFeatures)) {
        rename(from: STUBS_DIR.'/dependabot.yml.stub', to: GITHUB_DIR.'/dependabot.yml');

        rename(from: STUBS_DIR.'/dependabot-auto-merge.yml.stub', to: GITHUB_DIR.'/workflows/dependabot-auto-merge.yml');
    }

    if (in_array(needle: 'Update CHANGELOG', haystack: $enabledFeatures)) {
        rename(from: STUBS_DIR.'/update-changelog.yml.stub', to: GITHUB_DIR.'/workflows/update-changelog.yml');
    }

    rename(from: STUBS_DIR.'/bug_report.yml.stub', to: GITHUB_DIR.'/ISSUE_TEMPLATE/bug_report.yml');
}

/**
 * Build composer data array.
 */
function buildComposerData(array $details, array $features): array
{
    extract($details);
    extract($features);

    $data = [
        'name' => "$vendorNameSlug/$packageName",
        'description' => $packageDescription,
        'keywords' => ['laravel', $packageName],
        'homepage' => "https://github.com/$vendorNameSlug/$packageName",
        'license' => 'MIT',
        'authors' => [['name' => $packageAuthorName, 'email' => $email]],
        'require' => [
            'php' => "^$phpVersion",
            'illuminate/support' => "^$laravelVersion.0",
        ],
        'require-dev' => [
            'nunomaduro/collision' => '^8.0',
            'orchestra/testbench' => '^10.0',
        ],
        'autoload' => [
            'psr-4' => [
                "$vendorName\\".camelCase($packageName, true).'\\' => 'src/',
                "$vendorName\\".camelCase($packageName, true).'\\Database\\Factories\\' => 'database/factories/',
            ],
        ],
        'autoload-dev' => [
            'psr-4' => [
                "$vendorName\\".camelCase($packageName, true).'\\Tests\\' => 'tests/',
            ],
        ],
        'scripts' => [
            'post-autoload-dump' => [
                'Illuminate\\Foundation\\ComposerScripts::postAutoloadDump',
            ],
            'post-update-cmd' => [
            ],
        ],
        'config' => [
            'sort-packages' => true,
        ],
        'extra' => [
            'laravel' => [
                'providers' => ["$vendorName\\".camelCase($packageName, true)."\\{$className}ServiceProvider"],
                'aliases' => ["$className" => "$vendorName\\".camelCase($packageName, true)."\\Facades\\$className"],
            ],
        ],
        'minimum-stability' => 'stable',
        'prefer-stable' => true,
    ];

    // Pest-related dependencies and config
    if ($testingFramework === 'Pest') {
        $data['require-dev'] = array_merge($data['require-dev'], [
            'pestphp/pest' => '^3.0',
            'pestphp/pest-plugin-arch' => '^3.0',
            'pestphp/pest-plugin-laravel' => '^3.0',
        ]);

        $data['config']['allow-plugins']['pestphp/pest-plugin'] = true;

        $data['scripts']['test'] = 'vendor/bin/pest';
    } else {
        // PHPUnit as default
        $data['require-dev']['phpunit/phpunit'] = '^12.0';
        $data['scripts']['test'] = '@php vendor/bin/phpunit';
    }

    // Rector (optional)
    if (in_array('Rector', $enabledFeatures)) {
        $data['require-dev']['driftingly/rector-laravel'] = '^2.0';
        $data['require-dev']['rector/rector'] = '^2.0';
        $data['scripts']['refactor'] = 'rector';
        $data['scripts']['refactor:dry'] = 'rector --dry-run';
    }

    // Remove refactor scripts if Rector is not enabled
    if (! in_array('Rector', $enabledFeatures)) {
        unset($data['scripts']['refactor'], $data['scripts']['refactor:dry']);
    }

    // Pint (optional)
    if (in_array('Pint', $enabledFeatures)) {
        $data['require-dev']['laravel/pint'] = '^1.0';
        $data['scripts']['lint'] = './vendor/bin/pint';
        $data['scripts']['lint:test'] = './vendor/bin/pint --test';
    }

    // PHPStan (optional)
    if (in_array('PHPStan', $enabledFeatures)) {
        $data['require-dev']['phpstan/phpstan'] = '^2.0';

        if ($enableLarastan ?? false) {
            $data['require-dev']['larastan/larastan'] = '^3.0';
        }
    }

    return $data;
}

/**
 * Scaffold Facade and ServiceProvider.
 */
function scaffoldFacadeAndProvider(array $details): void
{
    $vendorName = $details['vendorName'];
    $packageName = camelCase($details['packageName'], true);
    $className = $details['className'];
    $packageNameKebab = kebabCase($packageName);

    $facadeCode = <<<EOT
<?php

namespace $vendorName\\$packageName\\Facades;

use Illuminate\\Support\\Facades\\Facade;

class $className extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return '$packageNameKebab';
    }
}
EOT;

    file_put_contents(SRC_DIR."/Facades/$className.php", $facadeCode);

    $serviceProviderCode = <<<EOT
<?php

namespace $vendorName\\$packageName;

use Illuminate\\Support\\ServiceProvider;

class {$className}ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ...
    }
}
EOT;

    file_put_contents(SRC_DIR."/{$className}ServiceProvider.php", $serviceProviderCode);
}

/**
 * Scaffold test files.
 */
function scaffoldTests(array $details): void
{
    rename(STUBS_DIR.'/Arch.php.stub', TESTS_DIR.'/Arch.php');

    rename(STUBS_DIR.'/TestCase.php.stub', TESTS_DIR.'/TestCase.php');

    replaceInFile(
        [':vendorName', ':packageName', ':packageNameServiceProvider'],
        [$details['vendorName'], camelCase($details['packageName'], true), $details['className'].'ServiceProvider'],
        TESTS_DIR.'/TestCase.php'
    );

    if ($details['testingFramework'] === 'PHPUnit') {
        rename(STUBS_DIR.'/run-tests.yml.stub', GITHUB_DIR.'/workflows/run-tests.yml');

        replaceInFile(
            [':phpVersion', ':laravelVersion'],
            [$details['phpVersion'], $details['laravelVersion']],
            GITHUB_DIR.'/workflows/run-tests.yml'
        );
    }

    if ($details['testingFramework'] === 'Pest') {
        touch(TESTS_DIR.'/Pest.php');

        file_put_contents(
            TESTS_DIR.'/Pest.php',
            "<?php\n\nuse {$details['vendorName']}\\".camelCase($details['packageName'], true)."\\Tests\\TestCase;\n\nuses(TestCase::class)->in(__DIR__);\n"
        );

        rename(STUBS_DIR.'/run-pest.yml.stub', GITHUB_DIR.'/workflows/run-pest.yml');

        replaceInFile(
            [':phpVersion', ':laravelVersion'],
            [$details['phpVersion'], $details['laravelVersion']],
            GITHUB_DIR.'/workflows/run-pest.yml'
        );
    }
}

/**
 * Scaffold feature files (Pint, PHPStan, Rector, etc.).
 */
function scaffoldFeatureFiles(array $features, array $details): void
{
    $enabledFeatures = $features['enabledFeatures'];

    if (in_array('Pint', $enabledFeatures)) {
        rename(STUBS_DIR.'/run-linter.yml.stub', GITHUB_DIR.'/workflows/run-linter.yml');
    }

    if (in_array('PHPStan', $enabledFeatures)) {
        rename(STUBS_DIR.'/phpstan.neon.stub', 'phpstan.neon');

        rename(STUBS_DIR.'/static-analysis.yml.stub', GITHUB_DIR.'/workflows/static-analysis.yml');

        file_put_contents('phpstan.neon',
            "includes: - ./vendor/nunomaduro/larastan/extension.neon\n\n".file_get_contents('phpstan.neon'));

        replaceInFile([':phpVersion'], [$details['phpVersion']], GITHUB_DIR.'/workflows/static-analysis.yml');
    }

    if (in_array('Rector', $enabledFeatures)) {
        rename(STUBS_DIR.'/rector.php.stub', 'rector.php');

        replaceInFile(':laravelVersion', $details['laravelVersion'], 'rector.php');
    }
}

/**
 * Install dependencies.
 */
function installDependencies(array $details): void
{
    $confirmInstall = confirm(label: 'Are you ready to install the dependencies?');

    if ($confirmInstall) {
        spin(callback: function () use ($details) {
            run('composer update --quiet --no-interaction');

            rename(STUBS_DIR.'/PULL_REQUEST_TEMPLATE.md.stub', GITHUB_DIR.'/PULL_REQUEST_TEMPLATE.md');

            rename(STUBS_DIR.'/README.md.stub', 'README.md');

            replaceInFile(
                [':username', ':packageName', ':laravelVersion', ':which-test'],
                [
                    $details['username'], kebabCase($details['packageName']), $details['laravelVersion'],
                    $details['testingFramework'] === 'Pest' ? 'pest' : 'tests',
                ],
                'README.md'
            );
        }, message: 'Installing dependencies...');
    }
}

/**
 * Optionally delete the installer.
 */
function cleanupInstaller(): void
{
    if (confirm(label: 'Do you want to delete the installer?')) {
        unlink('build.php');

        warning(message: 'The installer has been deleted');
    }
}

/**
 * Optionally delete the stubs directory.
 */
function cleanupStubs(): void
{
    if (confirm(label: 'Do you want to delete the stubs?')) {
        run('rm -rf stubs');

        warning(message: 'The stubs folder has been deleted');
    }
}

// Entrypoint
main();
