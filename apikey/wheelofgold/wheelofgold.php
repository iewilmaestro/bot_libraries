<?php
error_reporting(0);

const
title = "wheelofgold",
versi = "1.0.1",
class_require = "1.0.9",
host = "https://wheelofgold.com/",
refflink = "https://wheelofgold.com/?r=9964",
youtube = "https://youtube.com/@iewil";

require "../../class.php";

class Bot {
	public $cookie,$uagent;
	public function __construct(){
		
		Display::Ban(title, versi);
		cookie:
		if(empty(Functions::getConfig('cookie'))){
			Display::Cetak("Register",refflink);
			Display::Line();
		}
		$this->cookie = Functions::setConfig("cookie");
		$this->uagent = Functions::setConfig("user_agent");
		$this->iewil = new premium();
		$this->scrap = new HtmlScrap();
		
		Display::Ban(title, versi);
		
		$r = $this->Dashboard();
		if($r['Logout']){
			Functions::removeConfig("cookie");
			Functions::removeConfig("user_agent");
			print Display::Error("Cookie Expired\n");
			Display::Line();
			goto cookie;
		}
		
		//Display::Cetak("Bal_Api",$this->captcha->getBalance());
		Display::Line();
		
		$num = 0;
		$r = Requests::get(host,$this->headers())[1];
		preg_match_all('#https?:\/\/'.str_replace('.','\.',parse_url(host)['host']).'\/faucet\/currency\/([a-zA-Z0-9]+)#', $r, $matches);
		$this->coins = $matches[1];
		foreach($this->coins as $num => $coins){
			Display::Menu(($num+1), $coins);
		}
		Display::Menu(($num+=2), "All Coins");
		print Display::Isi("Nomor");
		$pil = readline();
		Display::Line();
		
		$numExec = $pil-1;
		if($pil == $num){
			$coin = $this->coins;
		}elseif($numExec){
			$coin[0] = $this->coins[($pil-1)];
		}else{
			$coin[0] = $this->coins[0];
		}

		if($this->Claim($coin)){
			Functions::removeConfig("user_agent");
			Functions::removeConfig("cookie");
			goto cookie;
		}
	}
	public function headers($data=0){
		$h[] = "Host: ".parse_url(host)['host'];
		if($data)$h[] = "Content-Length: ".strlen($data);
		$h[] = "User-Agent: ".$this->uagent;
		$h[] = "Cookie: ".$this->cookie;
		return $h;
	}
	
	public function Dashboard(){
		$r = Requests::get(host,$this->headers())[1];
		$logout = false;
		if(!preg_match('/Logout/',$r)){
			$logout = true;
		}
		return ["Logout" => $logout];
	}
	private function widgetId() {
		$uuid = '';
		for ($n = 0; $n < 32; $n++) {
			if ($n == 8 || $n == 12 || $n == 16 || $n == 20) {
				$uuid .= '-';
			}
			$e = mt_rand(0, 15);

			if ($n == 12) {
				$e = 4;
			} elseif ($n == 16) {
				$e = ($e & 0x3) | 0x8;
			}
			$uuid .= dechex($e);
		}
		return $uuid;
	}
	private function iconBypass($token){
		icon_bypass:
		$icon_header = $this->headers();
		$icon_header[] = "origin: ".host;
		$icon_header[] = "x-iconcaptcha-token: ".$token;
		$icon_header[] = "x-requested-with: XMLHttpRequest";
		$timestamp = round(microtime(true) * 1000);
		$initTimestamp = $timestamp - 2000;
		$widgetID = $this->widgetId();
		
		$data = ["payload" => 
			base64_encode(json_encode([
				"widgetId"	=> $widgetID,
				"action" 	=> "LOAD",
				"theme" 	=> "dark",
				"token" 	=> $token,
				"timestamp"	=> $timestamp,
				"initTimestamp"	=> $initTimestamp
			]))
		];
		
		$r = json_decode(base64_decode(Requests::post(host."captcha-request",$icon_header, $data)[1]),1);
		$base64Image = $r["challenge"];
		$challengeId = $r["identifier"];
		$cap = $this->iewil->IconCoordiant($base64Image);
		if(!$cap['x'])goto icon_bypass;
		
		$timestamp = round(microtime(true) * 1000);
		$initTimestamp = $timestamp - 2000;
		$data = ["payload" => 
			base64_encode(json_encode([
				"widgetId"		=> $widgetID,
				"challengeId"	=> $challengeId,
				"action"		=> "SELECTION",
				"x"				=> $cap['x'],
				"y"				=> 24,
				"width"			=> 320,
				"token" 		=> $token,
				"timestamp"		=> $timestamp,
				"initTimestamp"	=> $initTimestamp
			]))
		];
		$r = json_decode(base64_decode(Requests::post(host."captcha-request",$icon_header, $data)[1]),1);
		if(!$r['completed']){
			goto icon_bypass;
		}
		$data = [];
		$data['captcha'] = "iconcaptcha";
		$data['_iconcaptcha-token']=$token;
		$data['ic-rq']=1;
		$data['ic-wid'] = $widgetID;
		$data['ic-cid'] = $challengeId;
		$data['ic-hp'] = '';
		return $data;
	}
	public function Firewall(){
		while(1){
			$r = Requests::get(host."firewall",$this->headers())[1];
			$scrap = $this->scrap->Result($r);
			$data = $scrap['input'];
			
			if($scrap['captcha']['cf-turnstile']){
				$cap = $this->iewil->Turnstile($scrap['captcha']['cf-turnstile'], host);
				$data['cf-turnstile-response']=$cap;
			}else{
				print Display::Error("Sitekey Error\n"); 
				continue;
			}
			if(!$cap)continue;
			
			$r = Requests::post(host."firewall/verify",$this->headers(), http_build_query($data))[1];
			if(preg_match('/Invalid Captcha/',$r))continue;
			Display::Cetak("Firewall","Bypassed");
			Display::Line();
			return;
		}
	}
	private function Claim($coinss){
		while(true){
			$r = $this->Dashboard();
			if($r['Logout']){
				print Display::Error("Cookie Expired\n");
				Display::Line();
				return 1;
			}
			foreach($coinss as $a => $coin){
				$r = Requests::get(host."faucet/currency/".$coin,$this->headers())[1];
				$scrap = $this->scrap->Result($r);
				if($scrap['title'] == "404 Error Page"){
					unset($coinss[$a]);
					print Display::Error($coin." 404 Error Page\n");
					Display::Line();
					continue;
				}
				
				if($scrap['firewall']){
					print Display::Error("Firewall Detect\n");
					$this->Firewall();
					continue;
				}
				if($scrap['cloudflare']){
					print Display::Error(host."faucet/currency/".$coin.n);
					print Display::Error("Cloudflare Detect\n");
					Display::Line();
					return 1;
				}
				
				// Mesasge
				if(preg_match("/You don't have enough energy for Auto Faucet!/",$r)){exit(Error("You don't have enough energy for Auto Faucet!\n"));}
				if(preg_match('/Daily claim limit/',$r)){
					unset($coinss[$a]);
					Display::Cetak($coin,"Daily claim limit");
					Display::Line();
					continue;
				}
				$status_bal = explode('</span>',explode('<span class="badge badge-danger">',$r)[1])[0];
				if($status_bal == "Empty"){
					unset($coinss[$a]);
					Display::Cetak($coin,"Sufficient funds");
					Display::Line();
					continue;
				}
				
				// Delay
				$tmr = explode("-",explode('var wait = ',$r)[1])[0];
				if($tmr){
					Functions::Tmr($tmr);
					continue;
				}
				$post = explode('"', explode('action="', $r)[1])[0];
				// Exsekusi
				$data = $scrap['input'];
				
				if(explode('rel=\"',$r)[1]){
					$antibot = $this->iewil->AntiBot($r);
					if(!$antibot)continue;
					$data['antibotlinks'] = str_replace("+"," ",$antibot);
				}
				if($scrap['input']['_iconcaptcha-token']){
					$icon = $this->iconBypass($scrap['input']['_iconcaptcha-token']);
					if(!$icon)continue;
					$data = array_merge($data,$icon);
				}
				if($scrap['captcha']){
					if($scrap['captcha']['cf-turnstile']){
						$data['captcha'] = "CloudflareCaptcha";
						$cap = $this->iewil->Turnstile($scrap['captcha']['cf-turnstile'], host);
						if(!$cap)continue;
						$data['cf-turnstile-response']=$cap;
					
					/*
				}elseif($scrap['captcha']['h-captcha']){
					$data['captcha'] = "hcaptcha";
					print Display::Error();
					$cap = $this->captcha->Hcaptcha($scrap['captcha']['h-captcha'], host);
					$data['g-recaptcha-response']=$cap;
					$data['h-captcha-response']=$cap;
				}elseif($scrap['captcha']['authkong_captcha']){
					$data['captcha'] = "authkong";
					$cap = $this->captcha->Authkong($scrap['captcha']['authkong_captcha'], host);
					print_r($cap);
					$data['captcha-response']=$cap;
					*/
					
					}else{
						print_r($scrap['captcha']);
						print Display::Error("Sitekey Error\n"); 
						continue;
					}
				}
				if(!$data){
					print Display::Error("Data not found");
					sleep(3);
					print "\r                              \r";
					continue;
				}
				$data = http_build_query($data);
				$r = Requests::post($post,$this->headers(), $data)[1];
				$ban = explode('</div>',explode('<div class="alert text-center alert-danger"><i class="fas fa-exclamation-circle"></i> Your account',$r)[1])[0];
				$ss = explode(" was",explode("</i> 0.",$r)[1])[0];
				$wr = explode("</div>",explode("</i> Invalid ",$r)[1])[0];
				if($ban){
					print Display::Error("Your account".$ban.n);
					exit;
				}
				if(preg_match('/Shortlink in order to claim from the faucet!/',$r)){
					print Display::Error(explode("'",explode("html: '",$r)[1])[0]);
					exit;
				}
				if(preg_match('/sufficient funds/',$r)){
					unset($coinss[$a]);
					Display::Cetak($coin,"Sufficient funds");
					continue;
				}
				if($ss){
					Display::Cetak($coin," ");
					print Display::Sukses("0.".$ss);
					//Display::Cetak("Bal_Api",$this->captcha->getBalance());
					Display::Line();
				}elseif($wr){
					print Display::Error("Invalid ".$wr);
					sleep(3);
					print "\r                  \r";
				}else{
					print Display::Error("Server Down\n");
					sleep(3);
					print "\r                  \r";
				}
			}
			if(!$coinss){
				print Display::Error("All coins have been claimed\n");
				exit;
			}
		}
	}
}

new Bot();
