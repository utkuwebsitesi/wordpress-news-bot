<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class ImageValidator
{
    private const MIMES=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];

    /** @return array{mime:string,extension:string,width:int,height:int,hash:string} */
    public function validate(string$body,string$declaredMime,int$minWidth,int$minHeight):array
    {
        if($body==='')throw new RemoteAssetException('body_empty');
        $actual=(new \finfo(FILEINFO_MIME_TYPE))->buffer($body);$size=@getimagesizefromstring($body);
        if(!is_string($actual)||!isset(self::MIMES[$actual])||!is_array($size))throw new RemoteAssetException('mime_invalid');
        if(strtolower(trim($declaredMime))!==$actual)throw new RemoteAssetException('mime_mismatch');
        $width=(int)($size[0]??0);$height=(int)($size[1]??0);
        if($width<max(1,$minWidth)||$height<max(1,$minHeight))throw new RemoteAssetException('dimensions_too_small');
        return['mime'=>$actual,'extension'=>self::MIMES[$actual],'width'=>$width,'height'=>$height,'hash'=>hash('sha256',$body)];
    }
}
