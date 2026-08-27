<?php
declare(strict_types=1);
final class NextcloudClient {
    private string $baseUrl; private string $username; private string $password; private string $folder;
    public function __construct() {
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required.');
        $this->baseUrl = rtrim('https://nextcloud.zbradbury.com/remote.php/dav/files/webAdmin/', '/');
        $this->username = 'webAdmin';
        $this->password = 'ButcherTango!';
        $this->folder = 'crAssets';
    }
    public function upload(string $localFile, string $originalName, string $assetNumber, bool $useExactName=false): string {
        $allowedPattern = $useExactName ? '/[^A-Za-z0-9 ._()-]+/' : '/[^A-Za-z0-9._-]+/';
        $name = preg_replace($allowedPattern, '_', basename($originalName)) ?: 'attachment';
        $asset = preg_replace('/[^A-Za-z0-9._-]+/', '_', $assetNumber) ?: 'asset';
        $storedName = $useExactName ? $name : date('Ymd_His').'_'.bin2hex(random_bytes(4)).'_'.$name;
        $path = $this->folder.'/'.$asset.'/'.$storedName;
        $this->request('MKCOL', $this->folder, null, null, [201,405]);
        $this->request('MKCOL', $this->folder.'/'.$asset, null, null, [201,405]);
        $handle = fopen($localFile, 'rb');
        if ($handle === false) throw new RuntimeException('Unable to read the uploaded file.');
        try { $this->request('PUT', $path, $handle, (int)filesize($localFile), [200,201,204]); } finally { fclose($handle); }
        return $path;
    }
    public function stream(string $path): void {
        $this->request('GET', $path, null, null, [200], static function($curl, string $data): int { echo $data; return strlen($data); });
    }
    public function downloadToFile(string $path, string $localFile): void {
        $handle=fopen($localFile,'wb'); if($handle===false)throw new RuntimeException('Unable to create a temporary download file.');
        try{$this->request('GET',$path,null,null,[200],static function($curl,string $data)use($handle):int{return fwrite($handle,$data);});}finally{fclose($handle);}
    }
    public function delete(string $path): void { $this->request('DELETE', $path, null, null, [200,204,404]); }
    private function request(string $method, string $path, $body, ?int $size, array $ok, ?callable $writer=null): void {
        $url = $this->baseUrl.'/'.implode('/', array_map('rawurlencode', explode('/', trim($path, '/'))));
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_USERPWD=>$this->username.':'.$this->password, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>120, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_RETURNTRANSFER=>$writer===null]);
        if (is_resource($body)) { curl_setopt($curl, CURLOPT_UPLOAD, true); curl_setopt($curl, CURLOPT_INFILE, $body); curl_setopt($curl, CURLOPT_INFILESIZE, $size); }
        if ($writer !== null) curl_setopt($curl, CURLOPT_WRITEFUNCTION, $writer);
        $response = curl_exec($curl); $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $error = curl_error($curl); curl_close($curl);
        if ($response === false || !in_array($status, $ok, true)) throw new RuntimeException("Nextcloud request failed ({$status}): ".($error ?: 'unexpected response'));
    }
}
