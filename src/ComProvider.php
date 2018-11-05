<?php

namespace ComProvider;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use RecursiveDirectoryIterator as RDI;
use RecursiveIteratorIterator as RII;

class ComProvider implements PluginInterface, EventSubscriberInterface
{
    protected $composer;
    protected $io;
    public $vendorDirectory;
    public $configAppDirectory;
    public $installedPackage;
    public $packageDirectory;
    public $packageProvider;
    public $requiredPackages;
    public $files;
    const MOTTO = 'ComProvider';

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->initDirectories();
    }

    public function initDirectories()
    {
        $this->vendorDirectory = $this->composer->getConfig()->get('vendor-dir');
        $this->configAppDirectory = $this->vendorDirectory.'/../config/app.php';
    }

    public static function getSubscribedEvents()
    {
        return [
            'post-package-install' => [
                ['packageInstalled', 0],
            ],
        ];
    }

    public function getPackageName()
    {
        return $this->installedPackage->getName();
    }

    public function packageInstalled(PackageEvent $event)
    {
        $packages = $this->composer->getPackage()->getRequires();
        foreach ($packages as $key => $package) {
            $this->requiredPackages[] = $key;
        }
        $this->installedPackage = $event->getOperation()->getPackage();
        if (in_array($this->getPackageName(), $this->requiredPackages)) {
            $this->packageDirectory = $this->vendorDirectory.'/'.$this->getPackageName();
            if ($this->setFiles($this->directoryContents())) {
                $this->packageProvider = $this->getProvider($this->files);
                $this->addLine();
            }
        }
    }

    public function setFiles($contents)
    {
        if (!empty($contents)) {
            $this->files = $contents;

            return true;
        } else {
            return false;
        }
    }

    public function directoryContents()
    {
        $rii = new RII(new RDI($this->packageDirectory));
        $files = [];
        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }
            if (strpos($file->getPathname(), 'ServiceProvider') !== false) {
                if (strpos($file->getPathname(), 'Lumen') === false) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function getProvider($file)
    {
        $phpCode = file_get_contents(current($file));

        return $this->parseClass($phpCode);
    }

    public function parseClass($phpCode)
    {
        $class_name = '';
        $namespace = 0;
        $tokens = token_get_all($phpCode);
        $count = count($tokens);
        $dlm = false;
        for ($i = 2; $i < $count; $i++) {
            if ((isset($tokens[$i - 2][1]) && ($tokens[$i - 2][1] == 'phpnamespace' || $tokens[$i - 2][1] == 'namespace')) ||
                ($dlm && $tokens[$i - 1][0] == T_NS_SEPARATOR && $tokens[$i][0] == T_STRING)) {
                if (!$dlm) {
                    $namespace = 0;
                }
                if (isset($tokens[$i][1])) {
                    $namespace = $namespace ? $namespace.'\\'.$tokens[$i][1] : $tokens[$i][1];
                    $dlm = true;
                }
            } elseif ($dlm && ($tokens[$i][0] != T_NS_SEPARATOR) && ($tokens[$i][0] != T_STRING)) {
                $dlm = false;
            }
            if (($tokens[$i - 2][0] == T_CLASS || (isset($tokens[$i - 2][1]) && $tokens[$i - 2][1] == 'phpclass'))
                && $tokens[$i - 1][0] == T_WHITESPACE && $tokens[$i][0] == T_STRING) {
                $class_name = $tokens[$i][1];
            }
        }

        return $namespace.'\\'.$class_name.'::class, //  '.$this->getPackageName();
    }

    public function addLine()
    {
        $search = self::MOTTO;
        $lines = file($this->configAppDirectory);
        $line_number = false;
        while (list($key, $line) = each($lines) and !$line_number) {
            $line_number = (strpos($line, $search) !== false) ? $key + 1 : $line_number;
        }
        if ($line_number != false) {
            $provider = "\t\t".$this->packageProvider."\n";
            $this->insertValueAtPos($lines, $line_number + 1, $provider);
            $fp = fopen($this->configAppDirectory, 'w');
            foreach ($lines as $line) {
                fwrite($fp, $line);
            }
            fclose($fp);
        } else {
            echo 'ComProvider block not found in your config/app.php'.PHP_EOL;
        }
    }

    public function insertValueAtPos(array &$array, $pos, $value)
    {
        $maxIndex = count($array) - 1;
        if ($pos === 0) {
            array_unshift($array, $value);
        } elseif (($pos > 0) && ($pos <= $maxIndex)) {
            $firstHalf = array_slice($array, 0, $pos);
            $secondHalf = array_slice($array, $pos);
            $array = array_merge($firstHalf, [$value], $secondHalf);
        }
    }
}
