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
                if(strpos($file->getPathname(),'Lumen') === false){
                    $files[] = $file->getPathname();
                }
            }
        }
        return $files;
    }

    public function getProvider($file)
    {
        $php_code = file_get_contents(current($file));
        return $this->getPhpClasses($php_code);
    }


    public function getPhpClasses($phpcode) {

        $namespace = 0;
        $tokens = token_get_all($phpcode);
        $count = count($tokens);
        $dlm = false;
        for ($i = 2; $i < $count; $i++) {
            if ((isset($tokens[$i - 2][1]) && ($tokens[$i - 2][1] == "phpnamespace" || $tokens[$i - 2][1] == "namespace")) ||
                ($dlm && $tokens[$i - 1][0] == T_NS_SEPARATOR && $tokens[$i][0] == T_STRING)) {
                if (!$dlm) $namespace = 0;
                if (isset($tokens[$i][1])) {
                    $namespace = $namespace ? $namespace . "\\" . $tokens[$i][1] : $tokens[$i][1];
                    $dlm = true;
                }
            }
            elseif ($dlm && ($tokens[$i][0] != T_NS_SEPARATOR) && ($tokens[$i][0] != T_STRING)) {
                $dlm = false;
            }
            if (($tokens[$i - 2][0] == T_CLASS || (isset($tokens[$i - 2][1]) && $tokens[$i - 2][1] == "phpclass"))
                && $tokens[$i - 1][0] == T_WHITESPACE && $tokens[$i][0] == T_STRING) {
                $class_name = $tokens[$i][1];
                return $namespace.'\\'.$class_name.'::class,';
            }
        }
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