<?php



if (!function_exists('logger')) {
    /**
     * @param array ...$args
     */
    function logger(...$args)
    {
        if (php_sapi_name() === 'cli') {
            foreach ($args as $arg) {
                if (is_array($arg)) {
                    print_r($arg);
                } else
                    echo $arg;
                echo "\n";
                file_put_contents(LOG_FILENAME, $arg . "\n", FILE_APPEND);
            }
        } else {
            foreach ($args as $arg) {
                echo '<pre>';
                if (is_array($arg)) {
                    print_r($arg);
                } else
                    echo $arg;
                echo '<br>';
            }
        }
    }
}

if (!function_exists('translit')) {
    function translit($text, $lang = 'ru', $params = ['replace_space' => '-', 'replace_other' => '-'])
    {
        return CUtil::translit($text, $lang, $params);
    }
}

if (!function_exists('textlower')) {
    function textlower($text)
    {
        return mb_strtolower(trim($text));
    }
}

if (!function_exists('PR')) {
	function PR($o)
	{
		$bt =  debug_backtrace();
		$bt = $bt[0];
		$dRoot = $_SERVER["DOCUMENT_ROOT"];
		$dRoot = str_replace("/","\\",$dRoot);
		$bt["file"] = str_replace($dRoot,"",$bt["file"]);
		$dRoot = str_replace("\\","/",$dRoot);
		$bt["file"] = str_replace($dRoot,"",$bt["file"]);
    echo("------\n");
    echo('!!!error: '.$o . "\n");
    echo("file: ".$bt['file'].'['.$bt["line"]."]\n\n");
    
    // echo($o . "\n" . var_export($o, true));
    /*
		 <div style='font-size:9pt; color:#000; background:#fff; border:1px dashed #000;'>
			<div style='padding:3px 5px; background:#99CCFF; font-weight:bold;'>File: <?=$bt["file"]?> [<?=$bt["line"]?>]</div>
			<pre style='padding:10px;'><?print_r($o)?></pre>
		</div>
		*/
	}
}
