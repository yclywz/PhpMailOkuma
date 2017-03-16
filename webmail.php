<?php
require_once 'Zend/Loader.php';

/**
* imap veya pop3 sunucularından e-posta okumayı sağlar, smtp sunucusu üzerinden e-posta gönderir.
* @author Arda Beyazoğlu
* @version 1.0
*
*/
class WebMail {
   const TYPE_UID = 1;
   const TYPE_MSGNO = 2;
   const PROTOCOL_IMAP = 1;
   const PROTOCOL_POP3 = 2;
/**
* Kullanılan email protokolü: pop3 veya imap(varsayılan)
*
* @var string
*/
private $Protocol;
/**
* Mail sunucusuna açılan bağlantı
*
* @var Zend_Mail_Storage_Abstract
*/
private $Connection;
/**
* Mail sunucusu (örn: imap.gmail.com, mail.blabla.com)
*
* @var string
*/
private $Host;
/**
* Port numarası (varsayılan port, imap için 143, pop3 için 110 dur)
* google için imap:993, pop3:995
* @var int
*/
private $Port;
/**
* Güvenli bağlantı (örn. gmail sunucuları ssl kullanır)
*
* @var bool
*/
private $UseSSL;
/**
* Kullanıcı adı (test@gmail.com)
*
* @var string
*/
private $Username;
/**
* Şifre
*
* @var string
*/
private $Password;
/**
* Uzak sunucuya bağlantıyı oluşturur
*
* @param string $host: mail sunucusu (örn: imap.gmail.com)
* @param string $username: kullanıcı adı
* @param string $password: şifre
* @param bool $use_ssl: güvenli bağlantı
* @param int $port: port numarası (varsayılan imap: 143, pop3: 110) (google için imap: 993, pop3: 995)
* @param int $protocol: 1=imap, 2=pop3
*/
public function __construct($host, $username, $password, $use_ssl = false, $port = 143, $protocol = 1){
   $this->Host = $host;
   $this->Username = $username;
   $this->Password = $password;
   $this->Protocol = $protocol;
   $this->UseSSL = $use_ssl;
   $this->Port = $port;
   $this->Connection = $this->connect();
   
}
/**
* Uzak sunucuya bağlantıyı oluşturur
*
* @return Zend_Mail_Storage_Abstract
*/
private function connect(){
   $params = array(
      "host" => $this->Host,
      "user" => $this->Username,
      "password" => $this->Password
   );
   
   $classname = array('mbox'           => 'Zend_Mail_Storage_Mbox',
                      'mbox-folder'    => 'Zend_Mail_Storage_Folder_Mbox',
                      'maildir'        => 'Zend_Mail_Storage_Maildir',
                      'maildir-folder' => 'Zend_Mail_Storage_Folder_Maildir',
                      'pop3'           => 'Zend_Mail_Storage_Pop3',
                      'imap'           => 'Zend_Mail_Storage_Imap');
	Zend_Loader::loadClass($classname['imap']);
	Zend_Loader::loadClass('Zend_Mail_Storage');
   
   if($this->UseSSL) $params["ssl"] = "SSL";
   if($this->Protocol == self::PROTOCOL_POP3){
      $connection = new Zend_Mail_Storage_Pop3($params);
   }else{
      $connection = new Zend_Mail_Storage_Imap($params);
   }
   return $connection;
}
 
/**
 * Bağlantıyı kapatır
 *
 */
public function close(){
	$this->Connection->close();
}
 
/**
 * Bağlantıyı açık tutar, zaman aşımını engeller
 *
 */
public function keepAlive(){
	$this->Connection->noop();
}

public function read($msgno){
	// mesaj başlıkları alınır
	$message = $this->Connection->getMessage($msgno);
 
	if($this->Protocol == self::PROTOCOL_IMAP){
		// mesaj silinmişse veya 'x-seen' olarak belirlediğim bayrağa sahipse mesaj alınmıyor
		// Okundu, okunmadı vb. gibi bayraklar için Zend_Mail_Storage sınıfındaki sabitler kullanılabilir
		//if($message->hasFlag(Zend_Mail_Storage::FLAG_DELETED) || $message->hasFlag('$x-seen')) return false;
		//$this->setFlag('$x-seen', $msgno);
	}
 
	$subject_text = "";
	// mesajın konusu karakter kümesine göre okunur
	//$subject_decoded = imap_mime_header_decode($message->getHeader("subject"));
	//foreach ($subject_decoded as $subject){
	//	$subject_text .= $this->decodeString($subject->text, $subject->charset);
	//}
 
	// imap ile mesaja sonradan erişebilmek (silmek, okundu olarak işaretlemek gibi işlemler) için verilen bir id
	// genellikle mesajların kendine özel eşsiz bir kodu olur (uid ile karıştırılmamalıdır, bir işlevi yoktur)
	$uid = $this->Connection->getUniqueId($msgno);
 
	// genellikle mesajların kendine özel eşsiz bir id si olur
	if($message->headerExists("message-id")){
		$message_id = str_replace(array("<", ">"), "", $message->getHeader("message-id"));
	}else{
		$message_id = uniqid();
	}
 
	// mesajı gönderen bilgisi okunur
	$from = $this->decodeUser("from", $message);
	// yanıtlama adresi  okunur
	$reply_to = $this->decodeUser("reply-to", $message);
	// cc ve bcc bilgisi okunur
	$cc = $this->decodeUser("cc", $message);
	$bcc = $this->decodeUser("bcc", $message);
	// mesajın 'kime' kısmı yani kimlere gönderildiği bilgisi okunur
	$to = $this->decodeUser("to", $message);
 
	// mesajla ilgili veriler bir araya toplanır
	$msg = array(
		"uid" => $uid,
		"message_id" => $message_id,
		"subject" => $subject_text,
		"from" => $from[0],
		"to" => $to,
		"cc" => $cc,
		"bcc" => $bcc,
		"reply_to" => (!empty($reply_to)) ? $reply_to[0] : array(),
		"date" => $message->headerExists("date") ? $message->getHeader("date") : null,
		// buraya kadar alınan bilgiler için mesaj başlıklarını okumak yeterliydi
		// mesajın içeriğini, karakter kümesini ve eğer varsa eklentilerini okumak için daha fazlası gerekiyor
		"body_html" => "",
		"body_plain" => "",
		"charset" => "",
		// mesajın içine dahil edilmiş medya dosyaları
		"in_attachments" => array(),
		// mesajla birlikte gönderilmiş olan eklenti dosyaları
		"attachments" => array()
	);
 
	// mesajın eklentisi olup olmadığına bakılır
	if($message->isMultipart()){
		// email mesajları bazı parçalardan oluşur. html mesajı, text mesajı ve eklentilerin her biri ayrı birer parçadır.
		foreach (new RecursiveIteratorIterator($message) as $part) {
			$contentType = Zend_Mime_Decode::splitContentType($part->contentType);
 
			if(in_array($contentType["type"], array("text/plain", "text/html"))){
				// eğer bu parça eklenti değilse, belirtilen şifreleme yöntemine göre mesajın html veya text içeriği okunur
				$msg["content_type"] = $contentType["type"];
				$msg["charset"] = (!empty($contentType["charset"])) ? $contentType["charset"] : "UTF-8";
 
				$content = $part->getContent();
			    if($part->headerExists("content-transfer-encoding")){
			    	$encoding = $part->getHeader("content-transfer-encoding");
			    	if($encoding == Zend_Mime::ENCODING_QUOTEDPRINTABLE){
			    		$content = quoted_printable_decode($content);
			    	}else if($encoding == Zend_Mime::ENCODING_BASE64){
			    		$content = base64_decode($content);
			    	}
			    }
			    $content = $this->decodeString($content, $msg["charset"]);
 
			    $this->keepAlive();
 
			    if($contentType["type"] == "text/plain") $msg["body_plain"] = $content;
				else $msg["body_html"] = $content;
			}else{
				// eğer bu parça eklenti ise, ne tür bir eklenti olduğuna bakılır
				if($part->headerExists("content-disposition") || $part->headerExists("content-id")){
					if($part->headerExists("content-disposition")){
						$contentDisposition = Zend_Mime_Decode::splitHeaderField($part->getHeader("content-disposition"), null, "type");
					}else{
						$contentDisposition = array("type" => "inline");
					}
 
					if($contentDisposition["type"] == "attachment" || $contentDisposition["type"] == "inline"){
						$encoding = Zend_Mime::ENCODING_8BIT;
						if($part->headerExists("content-transfer-encoding")){
							$encoding = $part->getHeader("content-transfer-encoding");
						}
 
						$content = $part->getContent();
						if($encoding == Zend_Mime::ENCODING_QUOTEDPRINTABLE){
				    			$content = quoted_printable_decode($content);
				    		}else if($encoding == Zend_Mime::ENCODING_BASE64){
				    			$content = base64_decode($content);
					    	}
				    		$this->keepAlive();
 
				    		$content_id = ($part->headerExists("content-id")) ? $part->getHeader("content-id") : null;
				    		if($contentDisposition["type"] == "inline" || ($contentDisposition["type"] == "attachment" && !empty($content_id))){
				    			// eğer mesajın içeriğine eklenmiş bir eklenti ise, eklentinin binary kodu, dosya tipi gibi özellikler okunur
							$id = str_replace(array("<", ">"), "", $content_id);
							$ctype = strtok(trim($contentType["type"]), "/");
 
							if($ctype == "image"){
								$file_type = str_replace("jpeg", "jpg", strtok("/"));
							}else{
								if(array_key_exists("name", $contentType)) $name = $contentType["name"];
								else if(array_key_exists("filename", $contentType)) $name = $contentType["filename"];
								else $name = $contentDisposition["filename"];
 
								$fparts = explode(".", $name);
								$file_type = $fparts[count($fparts) - 1];
							}
 
					    		$msg["in_attachments"][] = array(
								"id" => $id,
								"filename" => "image_$id.$file_type",
								"content" => $content,
								"content_type" => $contentType["type"],
								"charset" => (isset($contentDisposition["charset"])) ? $contentDisposition["charset"] : "utf-8",
								"file_type" => $file_type,
					    		);
						}
 
						if($contentDisposition["type"] == "attachment"){
							// eklentinin dosya ismi, türü, binary kodu gibi bilgileri okunur
							$fname = isset($contentDisposition["filename"]) ? $contentDisposition["filename"] : uniqid();
							//$fname = imap_mime_header_decode($fname);
 
							$msg["attachments"][] = array(
								"id" => ($part->headerExists("x-attachment-id")) ? $part->getHeader("x-attachment-id") : uniqid(),
								"filename" => $this->decodeString($fname[0]->text, $fname[0]->charset),
								"content_type" => $contentType["type"],
								"content" => $content,
								"charset" => (isset($contentDisposition["charset"])) ? $contentDisposition["charset"] : "utf-8"
							);
						}
					}
				}
			}
		}
	}else{
		// bu kısım çalışıyor ise mesajda eklenti bulunmamaktadır ve mesajın içeriği okunur
		if($message->headerExists("content-type")){
			$contentType = Zend_Mime_Decode::splitContentType($message->contentType);
		}else{
			$contentType = array("type" => "text/plain", "charset" => "UTF-8");
		}
 
		$msg["content_type"] = $contentType["type"];
		$msg["charset"] = (!empty($contentType["charset"])) ? $contentType["charset"] : "UTF-8";
 
		$encoding = Zend_Mime::ENCODING_8BIT;
		if($message->headerExists("content-transfer-encoding"))
			$encoding = $message->getHeader("content-transfer-encoding");
 
		$content = $message->getContent();
		if($encoding == Zend_Mime::ENCODING_QUOTEDPRINTABLE){
    			$content = quoted_printable_decode($content);
	    	}else if($encoding == Zend_Mime::ENCODING_BASE64){
    			$content = base64_decode($content);
    		}
 
    		$this->keepAlive();
 
		$content = $this->decodeString($content, $msg["charset"]);
		if($contentType["type"] == "text/plain") $msg["body_plain"] = $content;
		else $msg["body_html"] = $content;
	}
 
	return $msg;
}

private function decodeString($str, $in_charset = "default", $out_charset = "UTF-8"){
	if(strtoupper($in_charset) == strtoupper($out_charset)) return $str;
 
	try {
		if($in_charset != "default"){
			$decoded_str = iconv($in_charset, "$out_charset//TRANSLIT", $str);
			if($decoded_str === false) throw new Exception("ifade dönüştürülemedi");
		}else{
			$decoded_str = mb_convert_encoding($str, $out_charset);
		}
	} catch (Exception $e) {
		try {
			if($in_charset != "default")
				$decoded_str = mb_convert_encoding($str, $out_charset, $in_charset);
		} catch (Exception $e) {
			$decoded_str = $str;
		}
	}
 
	return $decoded_str;
}
 
/**
 * Gönderen, gönderilen, cc, bcc ve yanıtlama adresine ait kişi bilgilerini alır
 *
 * @param string $header
 * @param Zend_Mail_Part $part
 * @return array
 */
private function decodeUser($header, $part){
	$info = array();
	if(!$part->headerExists($header)) return $info;
 
	$header = $part->getHeader($header);
	$split = explode(",", $header);
	foreach ($split as $key => $section){
		$subsection = explode(" ", $section);
		if(count($subsection) == 1){
			$info[$key]["email"] = str_replace(array("<", ">"), "", $subsection[0]);
			$info[$key]["name"] = strtok($info[$key]["email"], "@");
			$info[$key]["address"] = htmlspecialchars("<" . $info[$key]["email"] . ">");
		}else if(count($subsection) > 1){
			$info[$key]["email"] = str_replace(array("<", ">"), "", $subsection[count($subsection) - 1]);
			unset($subsection[count($subsection) - 1]);
			//$name = imap_mime_header_decode(implode(" ", $subsection));
			if(!empty($name)) $info[$key]["name"] = $this->decodeString($name[0]->text, $name[0]->charset);
			else $info[$key]["name"] = "";
			$info[$key]["address"] = htmlspecialchars($info[$key]["name"] . " <" . $info[$key]["email"] . ">");
		}else{
			$info[$key]["name"] = $section;
			$info[$key]["email"] = $section;
			$info[$key]["address"] = htmlspecialchars($section);
		}
	}
 
	return $info;
}
 
/**
 * Okundu, okunmadı gibi bilgileri gönderir
 *
 * @param string $flag
 * @param int $id
 * @param int $type (verilen id değerinin türünü belirtir: mesaj numarası veya uid)
 * @return bool
 */
public function setFlag($flag, $id, $type = 2){
	if($this->Protocol == self::PROTOCOL_POP3) return false;
 
	$flags = array($flag, '$x-seen');
	$flags = array_unique($flags);
 
	try {
		if($type == self::TYPE_UID) $id = $this->Connection->getNumberByUniqueId($id);
		return $this->Connection->setFlags($id, $flags);
	} catch (Exception $e) {
		return $e->getMessage();
	}
}
 
/**
* Mesajı siler
*
* @param int $id
* @param int $type (verilen id değerinin türünü belirtir: mesaj numarası veya uid)
* @return bool
*
public function delete($id, $type = 2){
	if($this->Protocol == self::PROTOCOL_POP3) return false;
 
	try {
		if($type == self::TYPE_UID) $id = $this->Connection->getNumberByUniqueId($id);
		return $this->Connection->removeMessage($id);
	} catch (Exception $e) {
		return $e->getMessage();
	}
}
*/
public function getMessageCount(){
   return $this->Connection->countMessages();
}

public function mailremove($id){
	$uniqueid = $this->Connection->getNumberByUniqueId($id);
	return $this->Connection->removeMessage($uniqueid);
}

public function htmlclear($veri){
	$message = str_replace('3D"','"',$veri);
				$message = str_replace('="="','="',$message);
				$message = str_replace('""','"',$message);
				$message = str_replace('3D','',$message);
				$message = str_replace('=09','',$message);
				$message = str_replace('=20','',$message);
				$message = str_replace('="','[@]',$message);
				$message = str_replace('=','',$message);
				$message = str_replace('[@]','="',$message);	
				$message = str_replace('\n','',$message);	
				$message = str_replace('\r','',$message);	
				preg_replace('~[\r\n]~', '', $message);
				$message = trim($message);
				$message = ltrim($message);
				$message = rtrim($message);
				$message = chop($message);	
	return $message;
}

}
?>