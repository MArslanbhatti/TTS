<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function fail(int $code,string $message):never {http_response_code($code);echo json_encode(['error'=>$message]);exit;}
if ($_SERVER['REQUEST_METHOD']!=='POST') fail(405,'POST required.');
$cfgPath=dirname(__DIR__,2).'/private/config.php';
if (!is_file($cfgPath)) fail(503,'Server not configured. Ask the administrator to set up Azure Speech.');
$cfg=require $cfgPath;
if (!is_array($cfg) || empty($cfg['azure_key']) || str_starts_with($cfg['azure_key'],'REPLACE_') || empty($cfg['secret']) || str_starts_with($cfg['secret'],'REPLACE_')) fail(503,'Azure Speech is not configured.');
if (!extension_loaded('curl')) fail(503,'PHP cURL extension is required.');
$length=(int)($_SERVER['CONTENT_LENGTH']??0);
if ($length>10000) fail(413,'Request too large.');
if (stripos($_SERVER['CONTENT_TYPE']??'', 'application/json')!==0) fail(415,'JSON required.');
$origin=$_SERVER['HTTP_ORIGIN']??'';
if ($origin!=='') { $host=parse_url($origin,PHP_URL_HOST); if (!$host || strcasecmp($host,explode(':',$_SERVER['HTTP_HOST']??'')[0])!==0) fail(403,'Cross-origin requests are not allowed.'); }
$input=json_decode(file_get_contents('php://input',false,null,0,10001),true);
if (!is_array($input)) fail(400,'Invalid JSON.');
$text=trim((string)($input['text']??''));
$voices=[
'en-US-JennyNeural'=>'en-US','en-US-GuyNeural'=>'en-US','en-GB-SoniaNeural'=>'en-GB',
'ur-PK-UzmaNeural'=>'ur-PK','ur-PK-AsadNeural'=>'ur-PK',
'hi-IN-SwaraNeural'=>'hi-IN','hi-IN-MadhurNeural'=>'hi-IN',
'ar-SA-ZariyahNeural'=>'ar-SA','ar-SA-HamedNeural'=>'ar-SA',
'es-ES-ElviraNeural'=>'es-ES','fr-FR-DeniseNeural'=>'fr-FR',
'de-DE-KatjaNeural'=>'de-DE','pt-BR-FranciscaNeural'=>'pt-BR',
'ja-JP-NanamiNeural'=>'ja-JP','zh-CN-XiaoxiaoNeural'=>'zh-CN'
];
$voice=(string)($input['voice']??'');
if (!isset($voices[$voice])) fail(400,'Unsupported voice.');
$chars=function_exists('mb_strlen')?mb_strlen($text,'UTF-8'):preg_match_all('/./us',$text);
$max=min(3000,max(100,(int)($cfg['max_chars_per_request']??1000)));
if ($chars<1 || $chars>$max) fail(400,"Enter 1 to $max characters.");
$speed=(float)($input['speed']??1);
if (!is_finite($speed)||$speed<0.7||$speed>1.3) fail(400,'Invalid speed.');
$region=(string)($cfg['azure_region']??'');
if (!preg_match('/^[a-z0-9]{2,25}$/',$region)) fail(503,'Invalid Azure region configuration.');
// Daily limits use a keyed IP fingerprint; never trust client-provided IP headers.
$ip=$_SERVER['REMOTE_ADDR']??'unknown';
$day=gmdate('Y-m-d');
$hash=hash_hmac('sha256',$day.'|'.$ip,$cfg['secret']);
$dir=$cfg['storage_dir']??dirname(__DIR__,2).'/private/usage';
if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) fail(503,'Usage storage unavailable.');
$path=rtrim($dir,'/').'/'.$day.'-'.$hash.'.json';
$f=fopen($path,'c+');if (!$f) fail(503,'Usage storage unavailable.');
if (!flock($f,LOCK_EX)){fclose($f);fail(503,'Usage storage busy.');}
$old=stream_get_contents($f);$used=json_decode($old?:'{}',true);
if (!is_array($used)) $used=[];
$count=(int)($used['requests']??0);$total=(int)($used['characters']??0);
$maxRequests=max(1,(int)($cfg['daily_requests']??10));
$maxDaily=max($max,(int)($cfg['daily_characters']??5000));
if ($count>=$maxRequests || $total+$chars>$maxDaily){flock($f,LOCK_UN);fclose($f);fail(429,'Daily free limit reached. Please try tomorrow (UTC).');}
// Reserve usage before upstream call to prevent concurrent abuse.
rewind($f);ftruncate($f,0);fwrite($f,json_encode(['requests'=>$count+1,'characters'=>$total+$chars]));fflush($f);flock($f,LOCK_UN);fclose($f);
$esc=fn($s)=>htmlspecialchars($s,ENT_XML1|ENT_QUOTES,'UTF-8');
$rate=round(($speed-1)*100);
$ssml='<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="'.$esc($voices[$voice]).'"><voice name="'.$esc($voice).'"><prosody rate="'.($rate>=0?'+':'').$rate.'%">'.$esc($text).'</prosody></voice></speak>';
$ch=curl_init('https://'.$region.'.tts.speech.microsoft.com/cognitiveservices/v1');
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$ssml,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>35,CURLOPT_HTTPHEADER=>['Ocp-Apim-Subscription-Key: '.$cfg['azure_key'],'Content-Type: application/ssml+xml','X-Microsoft-OutputFormat: audio-16khz-128kbitrate-mono-mp3','User-Agent: VoiceVanta/2.0'],CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_FOLLOWLOCATION=>false]);
$audio=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
if ($audio===false||$code!==200||strlen($audio)<100){error_log('VoiceVanta Azure TTS failed: HTTP '.$code.' '.$error);fail(502,'Voice generation temporarily unavailable. Please try again later.');}
header_remove('Content-Type');header('Content-Type: audio/mpeg');header('Content-Disposition: attachment; filename="voicevanta-speech.mp3"');header('Content-Length: '.strlen($audio));echo $audio;
