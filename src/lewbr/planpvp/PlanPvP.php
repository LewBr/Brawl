<?php
namespace lewbr\planpvp;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use lewbr\hg\tasks\WaitingTask;

class PlanPvP extends PluginBase implements Listener{
    public $brawl = [];
    public $cnt = [];
    public $money;
    public $amt;
    private $min;
    private $max;
    private $wait_time;
    public $game_time;
    public $running = false;
    #########################
    //game pos
    public $gx;
    public $gy;
    public $gz;
    public $glvl;
    ########################
    //wait pos
    private $wx;
    private $wy;
    private $wz;
    private $wlvl;
    #######################
    //lobby pos
    public $lx;
    public $ly;
    public $lz;
    public $llvl;
    public function onEnable(){
        $this->saveDefaultConfig();
        $this->money = $this->getConfig()->get("money_api");
        $this->amt = $this->getConfig()->get("money_amount");
        $this->min = $this->getConfig()->get("min_players");
        $this->max = $this->getConfig()->get("max_players");
        $this->gx = $this->getConfig()->get("game_x");
        $this->gy = $this->getConfig()->get("game_y");
        $this->gz = $this->getConfig()->get("game_z");
        $this->glvl = $this->getServer()->getLevelByName($this->getConfig()->get("game_level"));
        $this->wx = $this->getConfig()->get("wait_x");
        $this->wy = $this->getConfig()->get("wait_y");
        $this->wz = $this->getConfig()->get("wait_z");
        $this->wlvl = $this->getServer()->getLevelByName($this->getConfig()->get("wait_level"));
        $this->lx = $this->getConfig()->get("lobby_x");
        $this->ly = $this->getConfig()->get("lobby_y");
        $this->lz = $this->getConfig()->get("lobby_z");
        $this->llvl = $this->getServer()->getLevelByName($this->getConfig()->get("lobby_level"));
        $this->wait_time = $this->getConfig()->get("wait_time");
        $this->game_time = $this->getConfig()->get("game_time");
        $this->getServer()->getPluginManager()->registerEvents($this,$this);
    }

    public function pay($p,$amt,$api){
        $pl = $this->getServer()->getPluginManager();
        switch(strtolower($api)){
            case 'economyapi':#EcononmyAPI by onebone
                $api = $pl->getPlugin("EconomyAPI");
                if($api) $api->addMoney($p,$amt);
                break;
            case 'pocketmoney':#PocketMoney by MinecrafterJPN
                $api = $pl->getPlugin("PocketMoney");
                if($api) $api->grantMoney($p,$amt);
                break;
            case 'massiveeconomy':#MassiveEconomy by EvolSoft
                $api = $pl->getPlugin("MassiveEconomy");
                if($api) $api->payPlayer($p,$amt);
                break;
        }
    }
    public function createGameTask(){
        $this->running = true;
        $t = new GameTask($this);
        $h = $this->getServer()->getScheduler()->scheduleDelayedTask($t, 20*$this->game_time);
        $t->setHandler($h);
    }
    public function onCommand(CommandSender $runner, Command $call, $alia, array $arg){
        switch(strtolower($call->getName())){
            case 'planpvp':
                if(empty($arg) && $runner instanceof Player)$runner->sendMessage(TextFormat::RED."Use: /planpvp <entrar|sair>");
                if($runner->hasPermission("planpvp.cmd") && $runner instanceof Player && isset($arg[0])){
                    switch(strtolower($arg[0])){
                        case 'entrar':
                            if(!isset($this->brawl[$runner->getName()])) {
                                $this->cnt[$runner->getName()] = 0;
                                $this->brawl[$runner->getName()] = $runner;
                                $runner->teleport(new Position($this->wx,$this->wy,$this->wz,$this->wlvl),0,0);
                                foreach($this->brawl as $pl){
                                    $pl->sendMessage(TextFormat::GOLD."[PlanPvP] ".$runner->getName()." juntou-se ao jogo.");
                                }
                                if(count($this->brawl) >= $this->min){
                                    $t = new WaitTask($this);
                                    $h = $this->getServer()->getScheduler()->scheduleDelayedTask($t, 20*$this->wait_time);
                                    $t->setHandler($h);
                                    foreach($this->brawl as $pl) {
                                        $min = $this->wait_time/60;
                                        $pl->sendMessage(TextFormat::GOLD."[PlanPvP] Jogo inciando ".($this->wait_time <= 60 ? "{$this->wait_time} seconds" : "{$min} minutes."));
                                        break;
                                    }
                                }
                            }else{
                                $runner->sendMessage(TextFormat::RED."[PlanPvP] Você já juntou-se.");
                            }
                            if(count($this->brawl) >= $this->max){
                                $runner->sendMessage(TextFormat::RED."[PlanPvP] Jogo cheio.");
                            }
                            if($this->running){
                                $runner->sendMessage(TextFormat::RED."[PlanPvP] Jogo já em execução.");
                            }
                        break;
                        case 'sair':
                            if(isset($this->brawl[$runner->getName()])){
                                $runner->teleport(new Position($this->lx,$this->ly,$this->lz,$this->llvl),0,0);
                                $runner->sendMessage(TextFormat::GREEN."[PlanPvP] Teleportando...");
                                unset($this->brawl[$runner->getName()]);
                                unset($this->cnt[$runner->getName()]);
                                if(count($this->brawl) <= 0){
                                    $this->getServer()->broadcastMessage(TextFormat::GREEN."[PlanPvP] Jogo foi aberto.");
                                    $this->running = false;
                                    unset($this->brawl);
                                    unset($this->cnt);
                                }
                            }
                        break;
                    }
                }
            break;
        }
    }
    public function onQuit(PlayerQuitEvent $e){
        $p = $e->getPlayer();
        if(isset($this->brawl[$p->getName()])){
            unset($this->brawl[$p->getName()]);
            unset($this->cnt[$p->getName()]);
            if(count($this->brawl) <= 0){
                $this->getServer()->broadcastMessage(TextFormat::GREEN."[PlanPvP] Jogo foi aberto.");
                $this->running = false;
                unset($this->brawl);
                unset($this->cnt);
            }
        }
    }
    public function onKill(PlayerDeathEvent $e){
        $p = $e->getEntity();
        $c = $p->getLastDamageCause();
        if($p instanceof Player && $c instanceof Player){
            if(isset($this->brawl[$c->getName()])){
                $this->cnt[$c->getName()]+=1;
                foreach($this->brawl as $pl){
                    $pl->sendMessage(TextFormat::GREEN."[PlanPvP] ".$c->getName()." matou ".$p->getName().".");
                    $c->sendMessage(TextFormat::GREEN."[PlanPvP] Você está com ".$this->cnt[$c->getName()]." kills");
                    break;
                }
            }
        }
    }
}
