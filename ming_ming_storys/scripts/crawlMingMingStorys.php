<?php
/**
 * Created by PhpStorm.
 * User: Alvin Tang
 * Date: 2021/1/26
 * Time: 12:28
 * Email: pingtang000@foxmail.com
 * Desc: 明明讲故事抓取脚本
 */

$cli	=	php_sapi_name();
if($cli	!= 'cli'){
	exit('非法请求');
}

$fileName			=	'E:\\my\\projects\\web\\APlayer\\dist\\scripts\\mm_voices.js';

$existsJs			=	file_get_contents($fileName);
$existsVoiceStr		=	trim(preg_replace('/(var|voices=|;)/i', '', $existsJs));

$existsMusics		=	json_decode($existsVoiceStr,	true);
$existsMusics		=	$existsMusics	?:	[];

$baseUrl			=	'https://mp.weixin.qq.com/s/kU1uaxtR6TE10gnernCf2Q';

$baseContent		=	file_get_contents($baseUrl);

$baseContentPattern	=	'/<p\s+style="letter-spacing: 1.5px;margin-right: 1em;margin-left: 1em;".+?<\/p>/i';

$patternHref		=	'/http\:\/\/.+?[\'|"]/i';
$patternTitle		=	'/<span.+?<\/span>/i';
$patternClearHtml	=	'/<.+?>/i';
$patternClearQuot	=	'/(\'|")/i';
$patternReplaceAnd	=	'/\&amp;/i';
$patternConvert		=	'/transferTargetLink\s*=\s*[\'|"].+?[\'|"]/i';
$patternClearMoreHtml	=	'/(\&nbsp;)/i';
$successCount		=	0;


if(preg_match_all($baseContentPattern, $baseContent, $matchesBaseContent)){
	$tempMatchesBaseContent	=	$matchesBaseContent[0];
	$isBegin		=	false;
	foreach ($tempMatchesBaseContent as $matchItemBaseContent){

		if(stripos($matchItemBaseContent, '持续更新') !== false){
			$isBegin	=	true;
			continue;
		}

		if(!$isBegin){
			continue;
		}

		$title	=	trim(preg_replace($patternClearHtml, '', $matchItemBaseContent));
		$title	=	trim(preg_replace($patternClearMoreHtml, '', $matchItemBaseContent));

		if(empty($title)){
			echoNewLine(sprintf('获取标题为空.[%s]',	$matchItemBaseContent));
			continue;
		}

		$tempId			=	'i_' . intval($title);

		if($tempId <=	0){
			echoNewLine(sprintf('获取ID为空.[%s]-[%s]',	$title,	$matchItemBaseContent));
			continue;
		}

		if(array_key_exists($tempId,	$existsMusics)){
			echoNewLine(sprintf('已经存在该ID.[%s]-[%s]-[%s]',	$tempId,$title,	$matchItemBaseContent));
			continue;
		}

		if(preg_match($patternHref, $matchItemBaseContent, $matchesHref)){
			$tempHrefHtml	=	$matchesHref[0];
			$tempHref		=	trim(preg_replace($patternClearQuot, '', $tempHrefHtml));
			$tempHref		=	trim(preg_replace($patternReplaceAnd, '&', $tempHref));
		}

		if(empty($tempHref)){
			echoNewLine(sprintf('获取详情地址为空.[%s]-[%s]-[%s]',	$tempId,$title,	$matchItemBaseContent));
			continue;
		}

		$detailContent		=	file_get_contents($tempHref);
		$voiceID			=	getVoiceId($detailContent);
		if(empty($voiceID)){//公众号文章转移
			if(preg_match($patternConvert,	$detailContent, $matchesTransfer)){
				if(preg_match($patternHref,	$matchesTransfer[0],	$matchesConvertHttp)){
					$tempHref			=	trim(preg_replace($patternClearQuot, '', $matchesConvertHttp[0]));
					$tempHref			=	trim(preg_replace($patternReplaceAnd, '&', $tempHref));
					$detailContent		=	file_get_contents($tempHref);
					$voiceID			=	getVoiceId($detailContent);
				}
			}
		}

		if(empty($voiceID)){
			echoNewLine(sprintf('获取音频ID为空.[%s]-[%s]-[%s]-[%s]',	$tempHref,	$tempId,$title,	$matchItemBaseContent));
			continue;
		}

		$voiceUrl	=	'https://res.wx.qq.com/voice/getvoice?mediaid=' . $voiceID;

		$existsMusics[$tempId]	=	[
			'name'		=>	$title,
			'url'		=>	$voiceUrl,
			'cover'		=>	'images/sweet.jpg',
			'artist'	=>	'',
		];

		$successCount++;

		echoNewLine(sprintf('成功数：%s,标题：%s',	$successCount, $title));
	}
}

file_put_contents($fileName, sprintf('var voices=%s;', json_encode($existsMusics)));

echoNewLine('complete');

/**
 * 输出
 * @param $message
 */
function	echoNewLine($message){
	echo sprintf("%s\n", $message);
}

/**
 * 获取音乐ID
 * @param $content
 * @return mixed|string
 */
function	getVoiceId($content){
	$patternVoiceId		=	'/voice_encode_fileid=[\'|"].+?[\'|"]/';
	if(preg_match($patternVoiceId,	$content, $matchesVoiceId)){
		$tempVoiceId	=	preg_replace('/(voice_encode_fileid\=|\'|")/i', '', $matchesVoiceId[0]);
		$tempVoiceId	=	trim($tempVoiceId);
		return	$tempVoiceId;
	}

	return	'';
}