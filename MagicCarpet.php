<?php

/*
__PocketMine Plugin__
name=MagicCarpet
description=MagicCarpet is a plugin that allows the user to fly away on a carpet made of glass (port)
version=0.2
author=shoghicp
class=MagicCarpet
apiversion=7,8,9,10
*/

/* 
Small Changelog
===============

0.1:
- Alpha_1.2.2 compatible release

0.1.1:
- Small fixes

0.1.2
- Fixes
- Ability to place blocks in glass

0.1:
- Alpha_1.3 compatible release

*/



class MagicCarpet implements Plugin{
	private $api, $sessions;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->sessions = array();
	}
	
	public function init(){
		$this->api->addHandler("player.move", array($this, "handler"), 1);
		$this->api->addHandler("player.quit", array($this, "handler"), 1);
		$this->api->addHandler("player.block.break", array($this, "handler"), 20);
		$this->api->addHandler("player.block.break.invalid", array($this, "handler"), 20);
		$this->api->addHandler("player.block.place.invalid", array($this, "handler"), 20);
		$this->api->console->register("magiccarpet", "[size]", array($this, "command"));
		$this->api->console->alias("mc", "magiccarpet");
		$this->api->ban->cmdWhitelist("magiccarpet");
		console("[INFO] MagicCarpet enabled! Use /mc to toggle it");
	}
	
	public function __destruct(){

	}
	
	public function handler($data, $event){
		switch($event){
			case "player.move":
				if(isset($this->sessions[$data->player->CID])){
					$this->carpet($data);
				}
				break;
			case "player.quit":
				unset($this->sessions[$data->CID]);
				break;
			case "player.block.place.invalid":
				if(isset($this->sessions[$data["player"]->CID])){
					if(isset($this->sessions[$data["player"]->CID]["blocks"][$data["target"]->x.".".$data["target"]->y.".".$data["target"]->z])){
						return true;
					}
				}
				break;
			case "player.block.break.invalid":
			case "player.block.break":
				if(isset($this->sessions[$data["player"]->CID])){
					if(isset($this->sessions[$data["player"]->CID]["blocks"][$data["target"]->x.".".$data["target"]->y.".".$data["target"]->z])){
						unset($this->sessions[$data["player"]->CID]["blocks"][$data["target"]->x.".".$data["target"]->y.".".$data["target"]->z]);
					}
				}
				break;
		}
	}
	
	private function carpet(Entity $player){
					$session =& $this->sessions[$player->player->CID];
					$startX = (int) ($player->x - ($session["size"] - 1) / 2);
					$y = ((int) $player->y) - 1;
					if($player->pitch > 75){
						--$y;
					}
					$startZ = (int) ($player->z - ($session["size"] - 1) / 2);
					$endX = $startX + $session["size"];
					$endZ = $startZ + $session["size"];
					$newBlocks = array();
					for($x = $startX; $x < $endX; ++$x){
						for($z = $startZ; $z < $endZ; ++$z){
							$i = "$x.$y.$z";
							if(isset($session["blocks"][$i])){
								$newBlocks[$i] = $session["blocks"][$i];
								unset($session["blocks"][$i]);
							}else{
								$newBlocks[$i] = $player->level->getBlock(new Vector3($x, $y, $z));
								if($newBlocks[$i]->getID() === AIR){
									$session["blocks"][$i] = BlockAPI::get(GLASS);
									$session["blocks"][$i]->position(new Position($x, $y, $z, $player->level));
								}
							}
						}
					}

					foreach($session["blocks"] as $i => $block){
						$index = array_map("intval", explode(".", $i));
						foreach($this->api->player->getAll($player->level) as $p){
							$p->dataPacket(MC_UPDATE_BLOCK, array(
								"x" => $index[0],
								"y" => $index[1],
								"z" => $index[2],
								"block" => $block->getID(),
								"meta" => $block->getMetadata()		
							));
						}
					}
					$this->sessions[$player->player->CID]["blocks"] = $newBlocks;
	}
	
	public function command($cmd, $params, $issuer, $alias){
		$output = "";
		if($cmd === "magiccarpet"){
			if(!($issuer instanceof Player)){					
				$output .= "Please run this command in-game.\n";
				return $output;
			}
			$size = 5;
			if(isset($params[0])){
				$size = (int) $params[0];
			}
			switch($size){
				case 3:
				case 5:
				case 7:
					break;
				default:
					$size = 5;
					break;
			}
			
			if(!isset($this->sessions[$issuer->CID])){
				$this->sessions[$issuer->CID] = array(
					"size" => $size,
					"blocks" => array(),
				);
				$output .= "A glass carpet appears below your feet of size $size.\n";
				$this->carpet($issuer->entity);
			}else{
				foreach($this->sessions[$issuer->CID]["blocks"] as $i => $block){
					$index = array_map("intval", explode(".", $i));
					foreach($this->api->player->getAll($issuer->level) as $p){
						$p->dataPacket(MC_UPDATE_BLOCK, array(
							"x" => $index[0],
							"y" => $index[1],
							"z" => $index[2],
							"block" => $block->getID(),
							"meta" => $block->getMetadata()		
						));
					}
				}
				unset($this->sessions[$issuer->CID]);
				$output .= "The glass carpet dissapears.\n";
			}
		}
		return $output;
	}
}
