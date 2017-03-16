<?php 
include('webmail.php');

$host = "imap.yandex.com.tr";
$port = 993;
$username = "isim@alanadi.com";
$password = "sifre";
 
$mail = new WebMail($host, $username, $password, true, $port);
$count = $mail->getMessageCount(); 
if($count > 0){
	// döngünün 1 den başladığına dikkat edin. Mesaj numaraları 0 dan değil 1 den başlar.
	for($i=1; $i<=$count; $i++){
             	$message = $mail->read($i);
				//$message = $mail->read($_GET["id"]);
				print_r($message);
				
				/*
				//echo('<br><br>');
				//echo($message['from']["address"]);
				//print_r( html_entity_decode( $mail->htmlclear($message['body_html']) ));
				$content = $message['body_plain'];
				if($content == ""){
					$content = $message['body_html'];
				}
				
				$content = str_replace('<style type="text/css">','<style>',$content);
				$content = preg_replace("|<style\b[^>]*>(.*?)</style>|s", "", $content);
				$content = preg_replace("|<a\b[^>]*>(.*?)</a>|s", "", $content);
				$subject = $message["subject"];
				$subject = explode(' no’lu ',$subject);
				
				echo(content);
				
				/*
				if(str_replace(' ','',@$subject[1]) == 'kaydınızçözümlenmiştir.'){
					
					$pattern = '/Başlık: (.*)\'Nolu Görev/';
					preg_match($pattern, $content, $results);
					if(@$results[1] == ''){
						$pattern = '/[GOREVID](.*)[GOREVID]/';
						preg_match($pattern, $content, $results);
						
						if(@$results[1] == ''){
							$pattern = '/--(.*)--/';
							preg_match($pattern, $content, $results);
						}
					}
					$content = strip_tags(html_entity_decode( $mail->htmlclear($content)) ,'<p>');
				
					$gorevid = @$results[1];
					if( @is_int($gorevid) ){
						$sql = $db->query("select count(g.id) x,g.fk_kaydi_acan_agent agent from GorevDB g where g.id=" . $gorevid);
						$row = $db->fetch_array($sql);
						if($row['x'] != 0){
							
							$sql = $db->query("update GorevDB set fk_gorev_statu='3' where id=" . $gorevid);
							$html = $message["subject"];
							$html = $html . '<br>' . $content;
							$html = str_replace("\n","",$html);
							$db->query("insert into GorevDetay set fk_gorev=" . $gorevid . ", fk_agent='1', aciklama='" . trim(str_replace("'","&acute;",$html)) . "', fk_status='0'");
							
							
							
							
							
							$mailhtml = '<table width="100%" border="0" cellspacing="5" cellpadding="5">' .
							  '<tr>' .
								'<td width="40%" align="right">Service Manager Management : </td>' .
								'<td width="60%">' . $subject[0] . ' \' Nolu</td>' .
							  '</tr>' .
							  '<tr>' .
								'<td align="right">Statusu :</td>' .
								'<td>' . $gorevid . ' \' Nolu Görev Kapanmıştır.</td>' .
							  '</tr>' .
							  '<tr>' .
								'<td align="right" valign="top">Açıklama :</td>' .
								'<td>' . trim(str_replace("'","&acute;",$html)) . '</td>' .
							  '</tr>' .
							'</table>';
							
							$mailhtml = $mailhtml . '<br>Service Manager Management ' . $subject[0] . ' \' Nolu e posta için ' . $gorevid . ' \'nolu görev kapanmıştır.<br> E Posta UniqId : ' . $message['uid'] . ' - '  . $message['message_id']; 
							
							$msql = $db->query("select * from AgentDB where fk_tipi='5'");
							$xt   = 0;
							while($mrs = $db->fetch_array($msql)){
								if($xt == 0){
									$personelmail = $mrs["email"];
								}else{
									$personelmail = $personelmail . ';' . $mrs["email"];
								}
								$xt++;
							}
							
							
							$user_sql = $db->query("select a.email from GorevDB g
							left join AgentDB a on a.id= g.fk_kaydi_acan_agent
							where g.id=" . $gorevid);
							$user = $db->fetch_array($sql);
							
							SistemMailGonder($personelmail, $gorevid . "'Nolu Görev Kapatımı",$mailhtml);
							SistemMailGonder($user["email"] , $gorevid . "'Nolu Görev Kapatımı",$mailhtml);
							$mail->mailremove($message['uid']);
						}
					}
				}else{
					echo($message['uid'] . ' - ' . $message["subject"]);
					//$mail->mailremove($message['uid']);
				}
				//echo($mail->mailremove($message['uid']));
				
				//echo($content);
				*/
	}
}
?>
