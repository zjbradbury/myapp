<?php
declare(strict_types=1);
final class SimpleZip {
    private $handle; private array $entries=[]; private int $offset=0;
    public function __construct(string $path){$this->handle=fopen($path,'w+b');if($this->handle===false)throw new RuntimeException('Unable to create ZIP file.');}
    public function addFile(string $path,string $name):void{$name=str_replace('\\','/',basename($name));$size=(int)filesize($path);$crc=(int)hexdec(hash_file('crc32b',$path));$header=pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,0,$crc,$size,$size,strlen($name),0).$name;fwrite($this->handle,$header);$input=fopen($path,'rb');stream_copy_to_stream($input,$this->handle);fclose($input);$this->entries[]=[$name,$crc,$size,$this->offset];$this->offset+=strlen($header)+$size;}
    public function close():void{$centralStart=$this->offset;$centralSize=0;foreach($this->entries as [$name,$crc,$size,$offset]){$record=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,0,$crc,$size,$size,strlen($name),0,0,0,0,0,$offset).$name;fwrite($this->handle,$record);$centralSize+=strlen($record);}$count=count($this->entries);fwrite($this->handle,pack('VvvvvVVv',0x06054b50,0,0,$count,$count,$centralSize,$centralStart,0));fclose($this->handle);}
}
