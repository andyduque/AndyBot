<?php
// Tapatalk includes
include("./phpincludes/XMLRPC/xmlrpc.inc");
include("./phpincludes/XMLRPC/xmlrpcs.inc");
// Whatsapi requires
require './phpincludes/WHATSAPI/whatsprot.class.php';


// Release Notes V10 
// Remove individual board, make it group board only. Got rid of duplicate. 

// Release Notes V9
// removed XMPP, poitning to emergency board. 

// Release Notes V11
// skipped (aborted Digest test)

// Release Notes V12
// Added TWITCH monitor
// Added REDACTED Spoiler support. 
// Moved board to new URL (OscarMike)

// Release Notes V14
// Adding Ryan to TWITCH monitor
// Making sure that attachment photos from Board get posted as links in Whatsapp
// Put more visual separation when quoted sections from the board get posted to whatsapp
// (not done) Make network failure recovery a bit easier by forcing Andybot to scan back to find missed board posts. 
// Detect if Whatsapp posted links are images, and embed them in whats app as img tags. 
// Correcting I want to put the " URI so that Iphone, and Android users can direct launch twitch.


// Errors off
error_reporting(E_ALL ^ E_STRICT);

// Whatsapi Login Params
$nickname = "X";
$sender = 	"X"; // Mobile number with country code (but without + or 00)
$imei = 	"X"; // MAC Address for iOS IMEI for other platform (Android/etc)

$testingmode=false;


// Whatsapi calls
function fgets_u($pStdn)
{
    $pArr = array($pStdn);

    if (false === ($num_changed_streams = stream_select($pArr, $write = NULL, $except = NULL, 0))) {
        print("\$ 001 Socket Error : UNABLE TO WATCH STDIN.\n");

        return FALSE;
    } elseif ($num_changed_streams > 0) {
        return trim(fgets($pStdn, 1024));
    }
}


// Tapatalk calls
function xmlrpc_get_thread ($client,$topicid, $start, $end, $debug=0) {

$client->return_type = 'xmlrpcvals';
$client->setDebug($debug);
$msg =& new xmlrpcmsg('get_thread');
$p1 =& new xmlrpcval($topicid, 'string');
$msg->addparam($p1);
$p2 =& new xmlrpcval($start, 'int');
$msg->addparam($p2);
$p3 =& new xmlrpcval($end, 'int');
$msg->addparam($p3);

$res =& $client->send($msg, 0, 'https');
if ($res->faultcode()) return $res; else return php_xmlrpc_decode($res->value());

}


function xmlrpc_get_latest_topics ($client, $debug=0) {

$client->return_type = 'xmlrpcvals';
$client->setDebug($debug);
$msg =& new xmlrpcmsg('get_latest_topic');
$p1 =& new xmlrpcval(0, 'int');
$msg->addparam($p1);
$p2 =& new xmlrpcval(4, 'int');
$msg->addparam($p2);
$res =& $client->send($msg, 0, 'https');

	
if ($res->faultcode()) return $res; else return php_xmlrpc_decode($res->value());

}

function xmlrpc_login ($client, $ip1, $ip2, $debug=0) {

$client->return_type = 'xmlrpcvals';
$client->setDebug($debug);
$msg =& new xmlrpcmsg('login');
$p1 =& new xmlrpcval($ip1, 'base64');
$msg->addparam($p1);
$p2 =& new xmlrpcval($ip2, 'base64');
$msg->addparam($p2);
$res =& $client->send($msg, 0, 'https');
$cookies=$res->cookies();
$session_id = $cookies['XXXXXXXXX']['value'];
$client->setcookie('XXXXXXXX', $session_id);
	
if ($res->faultcode()) return $res; else return php_xmlrpc_decode($res->value());
}



function xmlrpc_reply ($client,$forum, $topic, $subject, $body, $debug=0) {

$client->return_type = 'xmlrpcvals';
$client->setDebug($debug);
$msg =& new xmlrpcmsg('reply_post');
$p1 =& new xmlrpcval($forum, 'string');
$msg->addparam($p1);
$p2 =& new xmlrpcval($topic, 'string');
$msg->addparam($p2);
$p3 =& new xmlrpcval($subject, 'base64');
$msg->addparam($p3);
$p4 =& new xmlrpcval($body, 'base64');
$msg->addparam($p4);
$res =& $client->send($msg, 0, 'https');

	
if ($res->faultcode()) return $res; else return php_xmlrpc_decode($res->value());

}

	

function startsWith($haystack, $needle)
{
    return !strncmp($haystack, $needle, strlen($needle));
}

// End Calls.
// Class Definitions:
class WhatsAppMessageHandler
{
    // property declaration
    public $group_message_buffer="";
	public $buffer_count=0;
	// Values for WhatsApp arena
	public $forum="5";
	public $topic="3";
	public $ignore="";
    // method declaration
	public function ProcessWhatsAppMessage(&$client,&$data,$latestTopic,$latestForum,&$wa)
	{

		if(!empty($data)) print_r($data);
        $v=count($data);
		for($i=0;$i<$v;$i++)
		{
			$protocolNode=$data[$i];
			$groupChat=false;
			$newPost=false;
			$newURL=false;
			$postFromAr=split("@",$protocolNode->_attributeHash['from']);
			$postFrom=$postFromAr[0];
			#echo "============POST FROM======".$postFrom;
			if($protocolNode->_attributeHash['type']=="chat")
			{
				if(isset($protocolNode->_attributeHash['author']))
				{
					// if it has an author, its a group chat. 
					// Changed. If its from the Single Shared Group chat, process like normal. We dont do special group chat processing anymore. 
					if($protocolNode->_attributeHash['from']=="1XXXXXXXXXX-1378157184@g.us")
					{
						$groupChat=false;
					}
					else
					{
						// We should never get here any more. 
						$groupChat=true;
					}
				}
				if(isset($protocolNode->_children))
				{
					$t=count($protocolNode->_children);
					for($j=0;$j<$t;$j++)
					{
						$childNode=$protocolNode->_children[$j];

						if($childNode->_tag == "notify")
						{
							$userposting = $childNode->_attributeHash['name'];
						}
						if($childNode->_tag == "body")
						{
							$postbody = $childNode->_data;
							$newPost=true;
						}
						if($childNode->_tag == "media")
						{
							if($mediaurl=$childNode->_attributeHash['type']== "image")
							{
								$mediaurl="[img]".$childNode->_attributeHash['url']."[/img]";
							}
							else
							{
								$mediaurl=$childNode->_attributeHash['url'];
							
							}
							echo $childNode->_attributeHash;
							$newURL=true;
						}
					}
					if($newPost || $newURL)
					{
						echo "=========================\n";
						if($groupChat)
						{
							echo "GROUP\n";
							if($newPost)
							{
								$this->group_message_buffer=$this->group_message_buffer.$userposting." - ". $postbody. "\n";
								$this->buffer_count++;
							}
							if($newURL)
							{
								$this->group_message_buffer=$this->group_message_buffer.$userposting." - ". $mediaurl. "\n";
								$this->buffer_count++;
							
							}
							echo "====".$this->buffer_count."====\n";
							
							if($this->buffer_count>15)
							{
								//Post Buffer
								echo $this->group_message_buffer;
								
								xmlrpc_reply($client,$this->forum, $this->topic,"WhatsApp",$this->group_message_buffer);
								$this->buffer_count=0;
								$this->group_message_buffer="";
							}
						}
						else
						{
							echo "INDIVIDUAL\n";
							if($newPost)
							{
								if(startsWith($postbody,"!!"))
								{
									if(startsWith($postbody,"!!status"))
									{
										//Special Command
										$wa->Message($postFrom, "Up and Running");

									}
									if(startsWith($postbody,"!!version"))
									{
										//Special Command
										$wa->Message($postFrom, "V7");

									}
									if(startsWith($postbody,"!!flush"))
									{
										//Special Command
										if($this->buffer_count>0)
										{			
											//Post Buffer
											echo $this->group_message_buffer;
											xmlrpc_reply($client,$this->forum, $this->topic,"WhatsApp",$this->group_message_buffer);
											$this->buffer_count=0;
											$this->group_message_buffer="";
										}
										$wa->Message($postFrom, "Flushed.");

									}
									if(startsWith($postbody,"!!shutdown"))
									{
										if(strcmp($postFrom,"1XXXXXXXXX")==0)
										{
											$wa->Message($postFrom, "Shutting Down.");
											exit;
										}
									}

								}
								else
								{
									$boardtext=$userposting." - ".$postbody;
									if($testingmode==true)
									{
										echo "======================\n".$boardtext;
										echo "\n======================\n";
									}
									else
									{
										xmlrpc_reply($client,$latestForum, $latestTopic,"WhatsApp",$boardtext);
									}
									//$ignore=$boardtext;
									//$wa->Message($postFrom, "Posted.");

								}
							}
							if($newURL)
							{
									$boardtext=$userposting." - ".$mediaurl;
									if($testingmode==true)
									{
										echo "======================\n".$boardtext;
										echo "\n======================\n";
									}
									else
									{
										xmlrpc_reply($client,$latestForum, $latestTopic,"WhatsApp",$boardtext);
									}
									//$ignore=$boardtext;
							}
							
							// If its an individual message, then its a command
							
					
						}
						echo $userposting." - ". $postbody. "\n";
						echo "=========================\n";
					}	
				}
			}
		}

	}
 
}





// Message Handler Init
$wah = new WhatsAppMessageHandler();


// Tapatalk Login and Array Initialization
$client =& new xmlrpc_client('/phpbb3/mobiquo/mobiquo.php', 'XXXXXX.XXXXXX.org', 0);
$client->setSSLVerifyHost(0);
$client->setSSLVerifyPeer(0);
$res=xmlrpc_login($client,"XXXXXX","XXXXXX",1);

// Init latest reply topic
$latestForum=3;
$latestTopic=-1;



// Example posting line: xmlrpc_reply($client,"2", "1291","Test","test123");

$res2=xmlrpc_get_latest_topics($client,1);

for( $i=0;$i<count($res2['topics']);$i++)
{
// If the topic is not sticky. 
	if($res2['topics'][$i]['is_sticky']==false)
	{
		$watched[$res2['topics'][$i]['topic_id']]=$res2['topics'][$i]['reply_number'];
		echo "Adding - ".$res2['topics'][$i]['topic_id']." - ".$res2['topics'][$i]['reply_number']."\n";
		// Set latest topic for posting from Whatsapp
		if($latestTopic<$res2['topics'][$i]['topic_id'])
		{
			$latestTopic=$res2['topics'][$i]['topic_id'];
		}
	}
}






// Whatsapi login and setup
echo "[] Logging in as '$nickname' ($sender)\n";
$wa = new WhatsProt($sender, $imei, $nickname, TRUE);
$wa->Connect();
$wa->Login();

$twitchaccounts= array("xxxxxx", "xxxxxx", "xxxxxx","xxxxxx","xxxxxx");

// Main Loop
echo "\n[] Listen mode:\n";
$counter=0;
$twitchcounter=0;
while (TRUE) {
    $wa->PollMessages();
    $data = $wa->GetMessages();
    //if(!empty($data)) print_r($data);
	$wah->ProcessWhatsAppMessage($client, $data,$latestTopic,$latestForum,$wa);
	
    sleep(5);
	$counter++;
	$twitchcounter++;
	if($counter >=3)
	{
		CheckBoard($client, $wa, $watched,$nickname,$latestTopic,$wah);
		$counter=0;
	}
	if($twitchcounter >=12)
	{	
		CheckTwitch($wa,$twitchstatus,$twitchaccounts);
		$twitchcounter=0;
	}
}
exit(0);

function CheckTwitch(&$wa,&$twitchstatus,&$twitchaccounts)
{
	for($t = 0 ; $t < count($twitchaccounts) ; $t++)
	{
		$channelName = $twitchaccounts[$t];
 
		$clientId = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXx';             // Register your application and get a client ID at http://www.twitch.tv/settings?section=applications

		$json_array = json_decode(file_get_contents('https://api.twitch.tv/kraken/streams/'.strtolower($channelName).'?client_id='.$clientId), true);
 
		if ($json_array['stream'] != NULL) {
			$channelTitle = $json_array['stream']['channel']['display_name'];
			$streamTitle = $json_array['stream']['channel']['status'];
			$currentGame = $json_array['stream']['channel']['game'];
			echo "$channelTitle is Online playing $currentGame\n";
			if($twitchstatus[$channelName]==0)
			{
				echo "sending\n";
				$postBody="$channelTitle is Online playing $currentGame @ twitch://stream/".$channelTitle;
				$wa->Message('1XXXXXXXXXX-1378157184',$postBody);
				//$wa->Message('XXXXXXXXXXX',$postBody);
			}
			$twitchstatus[$channelName]=1;

		} 
		else {
			echo "$channelName is Offline\n";
			if($twitchstatus[$channelName]==1)
			{
				echo "sending\n";
				$postBody="$channelName is Offline";
				$wa->Message('1XXXXXXXXXX-1378157184',$postBody);
				//$wa->Message('1XXXXXXXXXX',$postBody);
			}
			$twitchstatus[$channelName]=0;
		}
	}
}

function CheckBoard(&$client,&$wa, &$watched,$nickname,&$latestTopic,$wah)
{

	$res2=xmlrpc_get_latest_topics($client,0);
	
	for( $i=0;$i<count($res2['topics']);$i++)
	{
		// If the topic is not sticky. 
		if($res2['topics'][$i]['is_sticky']==false)
		{
			if(!isset($watched[$res2['topics'][$i]['topic_id']]))
			{
				$watched[$res2['topics'][$i]['topic_id']]=-1;
			}
			
		
			if($watched[$res2['topics'][$i]['topic_id']]<$res2['topics'][$i]['reply_number'])
			{
				echo "Found Not Equal ".$res2['topics'][$i]['topic_title']."\n";
				echo "Getting ".$watched[$res2['topics'][$i]['topic_id']]."===".$res2['topics'][$i]['reply_number']."\n";
				$res3=xmlrpc_get_thread($client,$res2['topics'][$i]['topic_id'],$watched[$res2['topics'][$i]['topic_id']]+1,$res2['topics'][$i]['reply_number'],0);
				
				
				

				
				for( $j=0;$j< count($res3['posts']);$j++)
				{
					echo "[********] Sending Message".$res3['posts'][$j]['post_author_name'].": ".$res3['posts'][$j]['post_content']."\n";
					//if(strcmp($res3['posts'][$j]['post_author_name'],$nickname)!=0)
					$GCforum="15";
					// If this is not a digest post
					if(strcmp($res3['posts'][$j]['forum_id'],$GCforum)!=0) 
					{
						//PreProcess PostContent
						$postBodyOrig=$res3['posts'][$j]['post_content'];
						
						// Process Post Body
						
	
		$postBody1 = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/', '$1', $postBodyOrig);
	$postBody2 = preg_replace('/\[img\](.*?)\[\/img\]/', ' $1 ', $postBody1);
	$postBody3 = preg_replace('/\[quote\]/', '"', $postBody2);
	$postBody4 = preg_replace('/\[spoiler\](.*?)\[\/spoiler\]/', '|REDACTED|', $postBody3);
	$postBody = preg_replace('/\[\/quote\]/', '" =====================', $postBody4);
						
						//Andy
						//$wa->Message('1XXXXXXXXXX', $res3['posts'][$j]['post_author_name'].": ".$postBody);
						
						if($testingmode==true)
						{
										echo "======================\n";
										$wa->Message('1XXXXXXXXXX', $res3['posts'][$j]['post_author_name'].": ".$postBody);
										echo "\n======================\n";
									}
									else
									{
						if(strcmp($res3['posts'][$j]['post_author_name'],'ghostclout') != 0)
						{
							$wa->Message('1XXXXXXXXXX-1378157184',$res3['posts'][$j]['post_author_name'].": ".$postBody);
						}
						}
					}
					else
					{
						echo "GHOSTCLOUTPOST**********************\n";
					}
				}	
				
				if($res2['topics'][$i]['topic_id'] > $latestTopic)
				{
					$latestTopic=$res2['topics'][$i]['topic_id'];
				}
				$watched[$res2['topics'][$i]['topic_id']]=$res2['topics'][$i]['reply_number'];
			}	
		}
	}

}
?>
