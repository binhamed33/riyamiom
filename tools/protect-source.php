<?php
/**
 * LexPro PHP Source Code Encoder
 * Encodes PHP files with base64+gzip compression and creates protected versions.
 * Run: php encode.php [--decode] [--source-dir=app] [--output-dir=encoded]
 */

class LexProEncoder
{
    private string $secretKey;
    private string $sourceDir;
    private string $outputDir;

    public function __construct(string $secretKey = null)
    {
        $this->secretKey = $secretKey ?: getenv('APP_KEY') ?: 'lexpro-default-key-change-me';
        $this->sourceDir = __DIR__;
        $this->outputDir = __DIR__ . '/encoded';
    }

    public function encode(string $sourceSubDir): void
    {
        $srcPath = $this->sourceDir . '/' . $sourceSubDir;
        if (!is_dir($srcPath)) {
            echo "Source directory not found: $srcPath\n";
            return;
        }

        $destPath = $this->outputDir . '/' . $sourceSubDir;
        if (!is_dir($destPath)) {
            mkdir($destPath, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $encoded = 0;
        $skipped = 0;

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                $skipped++;
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($srcPath) + 1);
            $destFile = $destPath . '/' . $relativePath;
            $destDir = dirname($destFile);

            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            $content = file_get_contents($file->getPathname());
            $encoded_content = $this->encodeContent($content);
            file_put_contents($destFile, $encoded_content);
            $encoded++;
        }

        echo "Encoded $encoded files, skipped $skipped non-PHP files\n";
        echo "Output: $this->outputDir/$sourceSubDir\n";
    }

    private function encodeContent(string $phpCode): string
    {
        $compressed = gzcompress($phpCode, 9);
        $encoded = base64_encode($compressed);
        $signature = hash_hmac('sha256', $encoded, $this->secretKey);

        $loader = '<?php' . PHP_EOL;
        $loader .= '// LexPro Protected Source - ' . date('Y-m-d H:i:s') . PHP_EOL;
        $loader .= '$_lpk=\'' . substr($signature, 0, 16) . '\';' . PHP_EOL;
        $loader .= '$d=base64_decode(\'' . $encoded . '\');' . PHP_EOL;
        $loader .= '$v=hash_hmac(\'sha256\',$d,\'' . substr($this->secretKey, 0, 16) . '\');' . PHP_EOL;
        $loader .= 'if(substr($v,0,16)!=$_lpk){http_response_code(403);exit;}echo gzuncompress($d);' . PHP_EOL;

        return $loader;
    }

    public function protectDirectory(string $dir): void
    {
        $webConfig = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $webConfig .= '<configuration>' . PHP_EOL;
        $webConfig .= '  <system.webServer>' . PHP_EOL;
        $webConfig .= '    <security>' . PHP_EOL;
        $webConfig .= '      <requestFiltering>' . PHP_EOL;
        $webConfig .= '        <fileExtensions>' . PHP_EOL;
        $webConfig .= '          <add fileExtension=".env" allowed="false"/>' . PHP_EOL;
        $webConfig .= '          <add fileExtension=".log" allowed="false"/>' . PHP_EOL;
        $webConfig .= '          <add fileExtension=".sqlite" allowed="false"/>' . PHP_EOL;
        $webConfig .= '          <add fileExtension=".db" allowed="false"/>' . PHP_EOL;
        $webConfig .= '        </fileExtensions>' . PHP_EOL;
        $webConfig .= '        <hiddenSegments>' . PHP_EOL;
        $webConfig .= '          <add segment=".env"/>' . PHP_EOL;
        $webConfig .= '          <add segment="vendor"/>' . PHP_EOL;
        $webConfig .= '          <add segment="storage"/>' . PHP_EOL;
        $webConfig .= '          <add segment="database"/>' . PHP_EOL;
        $webConfig .= '          <add segment="config"/>' . PHP_EOL;
        $webConfig .= '          <add segment="bootstrap"/>' . PHP_EOL;
        $webConfig .= '          <add segment="app"/>' . PHP_EOL;
        $webConfig .= '          <add segment="routes"/>' . PHP_EOL;
        $webConfig .= '        </hiddenSegments>' . PHP_EOL;
        $webConfig .= '      </requestFiltering>' . PHP_EOL;
        $webConfig .= '    </security>' . PHP_EOL;
        $webConfig .= '  </system.webServer>' . PHP_EOL;
        $webConfig .= '</configuration>';

        file_put_contents($this->sourceDir . '/public/web.config', $webConfig);
        echo "Created public/web.config for directory protection\n";
    }

    public function createHtaccess(): void
    {
        $htaccess = '# Deny access to sensitive files' . PHP_EOL;
        $htaccess .= '<FilesMatch "\.(env|log|sqlite|db)$">' . PHP_EOL;
        $htaccess .= '    Require all denied' . PHP_EOL;
        $htaccess .= '</FilesMatch>' . PHP_EOL;
        $htaccess .= PHP_EOL;
        $htaccess .= '# Deny access to hidden files' . PHP_EOL;
        $htaccess .= '<FilesMatch "^\.">' . PHP_EOL;
        $htaccess .= '    Require all denied' . PHP_EOL;
        $htaccess .= '</FilesMatch>' . PHP_EOL;

        file_put_contents($this->sourceDir . '/public/.htaccess', $htaccess);
        echo "Created public/.htaccess for Apache protection\n";
    }

    public function optimizeAutoload(): void
    {
        echo "Run: composer dump-autoload --optimize --no-dev\n";
        echo "This generates classmap for faster loading\n";
    }

    public function createOpCacheConfig(): void
    {
        $ini = '; LexPro OPcache Configuration' . PHP_EOL;
        $ini .= 'opcache.enable=1' . PHP_EOL;
        $ini .= 'opcache.memory_consumption=128' . PHP_EOL;
        $ini .= 'opcache.interned_strings_buffer=8' . PHP_EOL;
        $ini .= 'opcache.max_accelerated_files=10000' . PHP_EOL;
        $ini .= 'opcache.revalidate_freq=2' . PHP_EOL;
        $ini .= 'opcache.save_comments=0' . PHP_EOL;
        $ini .= 'opcache.enable_cli=0' . PHP_EOL;
        $ini .= 'opcache.validate_timestamps=0' . PHP_EOL;
        $ini .= 'opcache.jit_buffer_size=64M' . PHP_EOL;
        $ini .= 'opcache.jit=1255' . PHP_EOL;

        file_put_contents($this->sourceDir . '/php/opcache.ini', $ini);
        @mkdir($this->sourceDir . '/php');
        file_put_contents($this->sourceDir . '/php/opcache.ini', $ini);
        echo "Created php/opcache.ini - copy to PHP ini directory\n";
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $encoder = new LexProEncoder();

    $args = getopt('', ['source-dir:', 'output-dir:', 'protect', 'opcache']);

    if (isset($args['protect'])) {
        $encoder->protectDirectory('');
        $encoder->createHtaccess();
    }

    if (isset($args['opcache'])) {
        $encoder->createOpCacheConfig();
    }

    if (isset($args['source-dir'])) {
        $encoder->encode($args['source-dir']);
    } else {
        // Encode key directories
        $encoder->encode('app');
        $encoder->encode('routes');
        $encoder->encode('config');
    }
}
