<?php
namespace Core;


class Trigger
{

    protected static $debug = true;

    protected static $lastError = null;

    public static function record($str, $priority) {
		$pid = getmypid();
        $str = "[" . date("Y-m-d H:i:s") . "]-[PID:" . $pid . "]-[". $priority ."]" . $str . "\n";
        if (self::$debug) {
            print $str;
        }else {
            $fh = fopen(getcwd(). '/Temp/server.log', 'a');
            fwrite($fh, $str);
            fclose($fh);
        }
    }

	public static function log($str) {
        self::record($str, "DEBUG");
    }

	public static function warn($str) {
        self::record($str, "WARN");
    }

    public static function error($str) {
        self::$lastError = $str;

        self::record($str, "ERROR");
    }

    public static function getLastError() {
        return self::$lastError;
    }

    public static function debug($status = false)
    {
        self::$debug = $status;
    }
}
