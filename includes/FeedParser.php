<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class FeedParser
{
    public function parse(string $xml):array
    {
        libxml_use_internal_errors(true);$feed=simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOCDATA);
        if(!$feed)throw new \RuntimeException('RSS/Atom XML could not be read.');
        $items=[];$nodes=isset($feed->channel->item)?$feed->channel->item:(isset($feed->entry)?$feed->entry:($feed->item??[]));
        foreach($nodes as$item){
            $link=(string)($item->link['href']??$item->link??'');$title=trim(wp_strip_all_tags((string)($item->title??'')));if($title===''||$link==='')continue;
            $namespaces=$item->getDocNamespaces(true);$contentNamespace=isset($namespaces['content'])?$item->children($namespaces['content']):null;$encoded=(string)($contentNamespace?->encoded??'');$description=(string)($item->description??$item->summary??($encoded!==''?$encoded:($item->content??'')));$imageHtml=implode("\n",[(string)($item->description??''),(string)($item->summary??''),$encoded,(string)($item->content??'')]);
            $guid=trim((string)($item->guid??$item->id??$link));$dc=isset($namespaces['dc'])?$item->children($namespaces['dc']):null;$published=trim((string)($item->pubDate??$item->published??$item->updated??($dc?->date??'')));$category=trim(wp_strip_all_tags((string)($item->category['term']??$item->category??'')));
            [$imageUrl,$imageSource]=$this->image($item,$namespaces,$imageHtml);
            $items[]=['guid'=>$guid,'source_url'=>esc_url_raw($link),'title'=>$title,'excerpt'=>wp_trim_words(wp_strip_all_tags($description),55),'content_hash'=>hash('sha256',preg_replace('/\s+/u',' ',wp_strip_all_tags($description))?:$title),'source_category'=>$category,'published_at'=>$published,'image_url'=>$imageUrl,'image_source'=>$imageSource];
        }
        return$items;
    }

    private function image(\SimpleXMLElement$item,array$namespaces,string$html):array
    {
        foreach($namespaces as$prefix=>$uri){if($prefix!=='media'&&!str_contains(strtolower($uri),'search.yahoo.com/mrss'))continue;$item->registerXPathNamespace('wpnbmedia',$uri);foreach($item->xpath('./wpnbmedia:content')?:[]as$node){$url=(string)($node['url']??'');$type=strtolower((string)($node['type']??''));$medium=strtolower((string)($node['medium']??''));if($url!==''&&($type===''||str_starts_with($type,'image/')||$medium==='image'))return[$this->url($url),'media:content'];}foreach($item->xpath('./wpnbmedia:thumbnail')?:[]as$node){$url=(string)($node['url']??'');if($url!=='')return[$this->url($url),'media:thumbnail'];}}
        foreach($item->enclosure??[]as$node){$url=(string)($node['url']??'');$type=strtolower((string)($node['type']??''));if($url!==''&&str_starts_with($type,'image/'))return[$this->url($url),'enclosure'];}
        foreach($item->link??[]as$node){$url=(string)($node['href']??'');$type=strtolower((string)($node['type']??''));$rel=strtolower((string)($node['rel']??''));if($url!==''&&$rel==='enclosure'&&str_starts_with($type,'image/'))return[$this->url($url),'atom:enclosure'];}
        if(preg_match('~<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1~isu',$html,$match)){return[$this->url(html_entity_decode($match[2],ENT_QUOTES|ENT_HTML5,'UTF-8')),'html:img'];}
        return['',''];
    }

    private function url(string$url):string{$url=esc_url_raw(trim($url));return preg_match('~^https?://~i',$url)?$url:'';}
}
