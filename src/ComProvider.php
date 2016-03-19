<?php
namespace ComProvider;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\EventDispatcher\Event;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Installer\PackageEvent;


class ComProvider implements PluginInterface, EventSubscriberInterface
{
    protected $composer;
    protected $io;
    public $packages = [];

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function getSubscribedEvents()
    {
        return array(
            'post-package-install' => array(
                array('packageInstalled', 0)
            ),
        );
    }

    public function packageInstalled(PackageEvent $event)
    {
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        $configApp = $vendorDir . '/../config/app.php';
        $installedPackage = $event->getOperation()->getPackage();
        $name = $installedPackage->getName();

        $dir = $vendorDir . '/' . $name;
        $files = $this->directoryContents($dir);
        if (!empty($files)) {
            $provider = $this->getProvider($files);
            $this->saveLine($configApp, $provider, $dir);
        }
    }

    public function directoryContents($dir)
    {

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $files = array();
        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }
            if (strpos($file->getPathname(), 'ServiceProvider') !== false) {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    public function getProvider($file)
    {
        $php_code = file_get_contents(current($file));
        $classes = array();
        $namespace = "";
        $tokens = token_get_all($php_code);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < $count; ++$j) {
                    if ($tokens[$j][0] === T_STRING)
                        $namespace .= "\\" . $tokens[$j][1];
                    elseif ($tokens[$j] === '{' or $tokens[$j] === ';')
                        break;
                }
            }
            if ($tokens[$i][0] === T_CLASS) {
                for ($j = $i + 1; $j < $count; ++$j)
                    if ($tokens[$j] === '{') {
                        $classes[] = $namespace . "\\" . $tokens[$i + 2][1];
                    }
            }
        }
        return substr(current($classes), 1) . '::class,';
    }

    public function saveLine($file, $provider)
    {
        $search = "ComProvider";
        $lines = file($file);
        $line_number = false;
        while (list($key, $line) = each($lines) and !$line_number) {
            $line_number = (strpos($line, $search) !== FALSE) ? $key + 1 : $line_number;
        }
        $provider = "\t\t" . $provider . "\n";
        $this->insertValueAtPos($lines, $line_number + 1, $provider);
        $fp = fopen($file, 'w');
        foreach ($lines as $line) {
            fwrite($fp, $line);
        }
        fclose($fp);
    }

    public function insertValueAtPos(array &$array, $pos, $value)
    {
        $maxIndex = count($array) - 1;

        if ($pos === 0) {
            array_unshift($array, $value);
        } elseif (($pos > 0) && ($pos <= $maxIndex)) {
            $firstHalf = array_slice($array, 0, $pos);
            $secondHalf = array_slice($array, $pos);
            $array = array_merge($firstHalf, array($value), $secondHalf);
        }

    }
}