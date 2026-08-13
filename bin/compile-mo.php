<?php
declare(strict_types=1);

$po=$argv[1]??'';$mo=$argv[2]??'';
if(!is_file($po)||$mo===''){fwrite(STDERR,"Usage: php compile-mo.php input.po output.mo\n");exit(2);}
$source=file_get_contents($po);$messages=[''=>"Project-Id-Version: WordPress News Bot 0.3.0\nLanguage: tr_TR\nContent-Type: text/plain; charset=UTF-8\nPlural-Forms: nplurals=2; plural=(n > 1);\n"];
if(preg_match_all('/^msgid "((?:[^"\\\\]|\\\\.)*)"\Rmsgstr "((?:[^"\\\\]|\\\\.)*)"/m',$source,$matches,PREG_SET_ORDER))foreach($matches as$m){$id=stripcslashes($m[1]);if($id!=='')$messages[$id]=stripcslashes($m[2]);}
ksort($messages,SORT_STRING);$ids=array_keys($messages);$translations=array_values($messages);$count=count($ids);$offsetOriginals=28;$offsetTranslations=$offsetOriginals+$count*8;$stringOffset=$offsetTranslations+$count*8;$originalTable='';$translationTable='';$originalStrings='';$translationStrings='';$cursor=$stringOffset;
foreach($ids as$id){$originalTable.=pack('V2',strlen($id),$cursor);$originalStrings.=$id."\0";$cursor+=strlen($id)+1;}
$translationOffset=$cursor;foreach($translations as$text){$translationTable.=pack('V2',strlen($text),$translationOffset);$translationStrings.=$text."\0";$translationOffset+=strlen($text)+1;}
$binary=pack('V7',0x950412de,0,$count,$offsetOriginals,$offsetTranslations,0,0).$originalTable.$translationTable.$originalStrings.$translationStrings;
if(file_put_contents($mo,$binary)===false)throw new RuntimeException('MO file could not be written.');
echo $mo.PHP_EOL;
