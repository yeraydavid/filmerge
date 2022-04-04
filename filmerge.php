<?php

$ffmpeg = realpath(dirname(__FILE__))."\\ffmpeg\\bin\\ffmpeg"; // FFMPEG path
$mencoder = realpath(dirname(__FILE__))."\\mencoder\\mencoder"; // mencoder path

echo "Filmerge - A video mix & remap script\n";
echo "Usage: Filmerge video1 video2 temp_dir min_sequence_length max_sequence_length \n\n";

if(!isset($argv[1])) { echo("Please specify video 1 dir\n"); exit;}
if(!isset($argv[2])) { echo("Please specify video 2 dir\n"); exit;}
if(!isset($argv[3])) {	echo("Please specify temp & output dir\n"); exit; }
if(!isset($argv[4])) { 	echo("Please specify min seq length\n"); exit; }
if(!isset($argv[5])) { 	echo("Please specify max seq length\n"); exit; }


$video0 = $argv[1];
$video1 = $argv[2];
$tmp = $argv[3];
$minseq = $argv[4];
$maxseq = $argv[5];

$extensiones = Array ( 'wmv', 'WMV','flv', 'mp4', 'avi', 'mkv','FLV', 'MP4', 'AVI', 'MKV' ); // video extensionss

function getInfo($xyz)
{
	global $ffmpeg;	
	$search='/Duration: (.*?),/';
	preg_match($search, $xyz, $matches);
	$explode = explode(':', $matches[1]);
	$duration = intval($explode[2] + 60*$explode[1] + 3600*$explode[0]);
	
	$search='/Stream .* Video: ([\w_]*).*, (\d+)x(\d+).* ((.+) fps)*/';	
	preg_match($search, $xyz, $matches);
	
	$obj = array();
	$obj["duracion"] = $duration;
	$obj["width"] = intval($matches[2]);
	$obj["height"] = intval($matches[3]);
	
	$search='/max_volume: (-?\d+(?:\.\d+)?) dB/';	
	preg_match($search, $xyz, $matches);	
	$obj["volumen"] = floatval($matches[1]);
	return $obj;
}

$outw = -1;
$outh = -1;
$outdur = -1;

$infos = [];

function muestraInfo($file, $i)
{
	global $ffmpeg;
	global $outw;
	global $outh;
	global $outdur;
	global $infos;
	$xyz =  shell_exec("$ffmpeg -i \"$file\" -af 'volumedetect'  -f null /dev/null  2>&1");   
	//$xyz =  shell_exec("$ffmpeg -i \"$file\" 2>&1");   
	$res = getInfo ($xyz);
	$w = $res["width"];
	$h = $res["height"];
	$d = $res["duracion"];
	if($w > $outw)
		$outw = $w;
	if($h > $outh)
		$outh = $h;		
	if(($outdur == -1)||($outdur > $d))
		$outdur = $d;		
	$infos[$i] = $res;
	echo "$file: ".$d."s ".$w."x".$res["height"]." ".$res["volumen"]." dB\n";
}

/*function fabricaArrayFicheros($path, $exts)
{
	$files = array();
	$it = new RecursiveDirectoryIterator($path);
	foreach(new RecursiveIteratorIterator($it) as $file)
	{
		$ext = $file->getExtension();
		$siz = filesize($file->getRealPath());
		if (in_array($ext, $exts) && ($siz != 0))
			$files[] = $file;
	}
	return $files;
}*/

function generateFragment($time, $duration, $files, $i, $j, $frag, $durtotal)
{
	$porcent = intval(100 * $time / $durtotal)."%";
	echo "\n$porcent  Fragmento de $time a ".($time + $duration)." V:$i A:$j ";
	global $ffmpeg;
	global $outw;
	global $outh;
	global $tmp;
	global $infos;
	$general = " -y -loglevel fatal ";
	$in1 = "-ss $time -seek_timestamp 1 -i \"".$files[$i]."\"";
	$in2 = "-ss $time -seek_timestamp 1 -i \"".$files[$j]."\"";
	$gain = -$infos[$j]["volumen"];
	$outcodec = " -r 24 -c:v mpeg4 -vtag xvid -b:v 3M -c:a libmp3lame -b:a 192k -ar 44100  -ac 2 -af \"volume=".$gain."dB\" ";	
	echo " gain: $gain";
	$outscale = " -filter:v \"scale=iw*min($outw/iw\,$outh/ih):ih*min($outw/iw\,$outh/ih), pad=$outw:$outh:($outw-iw*min($outw/iw\,$outh/ih))/2:($outh-ih*min($outw/iw\,$outh/ih))/2\"";
	$outts = " ";
	$outmap = "-map 0:v:0 -map 1:a:0 -t $duration -shortest";
	exec("$ffmpeg $general $in1 $in2 $outcodec $outscale $outmap $outts   $tmp\\o$frag.avi");
}

 
echo "\n1: Locating files...\n";
$files = [];
$files[0] = $video0;
$files[1] = $video1;
$fc = count($files);

echo "\n2: Video processing\n\n";
$nn=1;
foreach($files as $key => $file)
{
	echo "(".$nn."/".$fc.") ";
	muestraInfo($file, $key);
	$nn++;
}
echo "\nOutput video info: $outw x $outh  $outdur s \n";

echo "\n3: Generating fragments\n";
$offset = 0;
$duracion = $outdur;
$v0 = 0;
$v1 = 1;
$frag = 0;
$cachos = " ";

while($offset < $duracion)
{
	$inc = rand($minseq, $maxseq);
	if($offset + $inc > $duracion)
		$inc = $duracion - $offset;
	$v0 = 1 - $v0;
	$v1 = 1 - $v1;
	generateFragment($offset, $inc, $files, $v0, $v1, $frag, $duracion);	
	$offset += $inc;	
	
	$cachos = $cachos." $tmp\\o$frag.avi";
	
	/* METODO CACHO A CACHO 
	if($frag == 1)
	{
		exec("$mencoder -msglevel all=0 -oac copy -ovc copy -idx -o $tmp\\videojammer_out.avi $tmp\\o0.avi $tmp\\o1.avi ");
		exec("del $tmp\\o0.avi");
		exec("del $tmp\\o1.avi");
	}
	else
		if($frag > 1)
		{
			exec("$mencoder -msglevel all=0 -oac copy -ovc copy -idx -o $tmp\\videojammer_tmp.avi $tmp\\videojammer_out.avi $tmp\\o$frag.avi ");
			exec("move /Y $tmp\\videojammer_tmp.avi $tmp\\videojammer_out.avi");
			exec("del $tmp\\o$frag.avi");
		}*/
	$frag++;	 
}

echo "\n4: Joining fragments\n";
exec("$mencoder -msglevel all=0 -oac copy -ovc copy -idx -o $tmp\\filmerge_out.avi $cachos ");

echo "\n\nVideo generated, exit";

?>