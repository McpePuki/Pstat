<?php
/**
 * @name PStat
 * @main PStat\PStat
 * @author Puki
 * @version 1.0.0
 * @api 3.10.5
 */
namespace PStat;

    use pocketmine\event\Listener;
    use pocketmine\plugin\PluginBase;
    use pocketmine\Server;
    use pocketmine\command\CommandSender;
    use pocketmine\command\Command;
    use pocketmine\entity\Entity;
    use pocketmine\Player;
    use pocketmine\scheduler\Task;
    use pocketmine\utils\Config;
    use pocketmine\event\player\PlayerJoinEvent;
    use pocketmine\event\entity\{
      EntityDamageByEntityEvent,EntityDamageEvent
    };

    use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
    use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
    use pocketmine\event\server\DataPacketReceiveEvent;

    class PStat extends PluginBase implements Listener {

      function onEnable() :void{
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->data = new Config ($this->getDataFolder() . 'StatData.yml', Config::YAML);
        $this->db = $this->data->getAll();

        $command = new \pocketmine\command\PluginCommand('스탯', $this);
        $command->setDescription('스탯 명령어');
        $this->getServer()->getCommandMap()->register('스탯', $command);
      }

      function save() : void{
        $this->data->getAll($this->db);
        $this->data->save();
      }

      function StatUI(Player $player){
        $statdata = $this->db['스탯'][$player->getName()];
        $atk = $statdata['공격력'];
        $def = $statdata['방어력'];
        $hp = $statdata['체력'];
        $button = [
          'type' => 'form',
          'title' => '< 스탯UI >',
          'content' => "
          §c▶ §f공격 스탯 : 1당 0.5 씩증가\n
          §c▶ §f방어 스탯은 1당 0.5 씩증가\n
          §c▶ §f체력 스탯은 1당 2 씩증가\n
          §b※ §f선택한 스탯은 복구를 못합니다. 창을닫으시려면 UI오른쪽 상단에 X표시를 누르세요.§b※
          ",
          'buttons' => [
            [
              'text' => "공격력\n현재{$atk}"
            ],
            [
              'text' => "방어력\n현재{$def}"
            ],
            [
              'text' => "체력\n현재{$hp}"
            ]
          ]
        ];
        return json_encode($button);
      }

      function onJoin(PlayerJoinEvent $event) : void{
        $player = $event->getPlayer();
        $name = $player->getName();
        if(isset($this->db['스탯'][$name])){
          $this->db['스탯'][$name] = [
            '공격력' => 0,
            '방어력' => 0,
            '체력' => 0
          ];
          $this->db['스탯포인트'][$name] = 0;
          $this->save();
        }else {
          $hp = ($this->db['스탯'][$player->getName()]['체력'] * 2) + $player->getMaxHealth();
          $player->setMaxHealth($hp);
        }
      }

      function onTouch(PlayerInteractEvent $event) :void{
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand()->getId();
        if($item == 1){
          $player->getInventory()->removeItem($player->getInventory()->getItemInHand()->setCount(1));
          $this->db['스탯포인트'][$player->getName()]++;
          $this->save();
          $player->sendMessage('성공적으로 스탯포인트 1을 얻었습니다.');
        }
      }

      function onDamage(EntityDamageEvent $event){
        if($event instanceof EntityDamageByEntityEvent){
          $entity = $event->getEntity();
          $damager = $event->getDamager();
          if($damager instanceof Player){
            if(!isset($this->db['스탯'][$damager->getName()])) return true;
            $damager_atk = $event->getBaseDamage() + $this->db['스탯'][$damager->getName()]['공격력'] / 2;
            $event->setBaseDamage($damager_atk);
          }
          if($entity instanceof Player){
            if(!isset($this->db['스탯'][$damager->getName()])) return true;
            $damager_def = $event->getBaseDamage() - $this->db['스탯'][$damager->getName()]['방어력'] / 2;
            $event->setBaseDamage($damager_def);
          }
        }
      }

      function UiEvent(DataPacketReceiveEvent $event) {
        $packet = $event->getPacket ();
        $player = $event->getPlayer ();
        if ($packet instanceof ModalFormResponsePacket) {
           if($packet->formId === 35350) {
          $button = json_decode ( $packet->formData, true );
          if(!isset($button)) return true;
          if($button === 0){
            if(!isset($this->db['스탯포인트'][$player->getName()])) return true;
            if($this->db['스탯포인트'][$player->getName()] <= 0) {
              $player->sendMessage('스탯 포인트가 부족합니다.');
              return true;
            }
            $this->db['스탯'][$player->getName()]['공격력']++;
            $this->save();
            $player->sendMessage('공격력 스탯 1이 상승 했습니다.');
            $pk = new ModalFormRequestPacket ();
            $pk->formId = 35350;
            $pk->formData = $this->StatUI($sender);
            $sender->dataPacket ($pk);
          }
          if($button === 1){
            if(!isset($this->db['스탯포인트'][$player->getName()])) return true;
            if($this->db['스탯포인트'][$player->getName()] <= 0) {
              $player->sendMessage('스탯 포인트가 부족합니다.');
              return true;
            }
            $this->db['스탯'][$player->getName()]['방어력']++;
            $this->save();
            $player->sendMessage('방어력 스탯 1이 상승 했습니다.');
            $pk = new ModalFormRequestPacket ();
            $pk->formId = 35350;
            $pk->formData = $this->StatUI($sender);
            $sender->dataPacket ($pk);
          }
          if($button === 2){
            if(!isset($this->db['스탯포인트'][$player->getName()])) return true;
            if($this->db['스탯포인트'][$player->getName()] <= 0) {
              $player->sendMessage('스탯 포인트가 부족합니다.');
              return true;
            }
            $this->db['스탯'][$player->getName()]['체력']++;
            $this->save();
            $hp = ($this->db['스탯'][$player->getName()]['체력'] * 2) + $player->getMaxHealth();
            $player->setMaxHealth($hp);
            $player->sendMessage('체력 스탯 1이 상승 했습니다.');
            $pk = new ModalFormRequestPacket ();
            $pk->formId = 35350;
            $pk->formData = $this->StatUI($sender);
            $sender->dataPacket ($pk);
          }
        }
      }
    }

      function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        $cmd = $command->getName();
        if($cmd == '스탯'){
          if(isset($args[0])) {
            $sender->sendMessage('/스탯 │ UI창을 띄웁니다.');
            return true;
          }
          $pk = new ModalFormRequestPacket ();
          $pk->formId = 35350;
          $pk->formData = $this->StatUI($sender);
          $sender->dataPacket ($pk);
          return true;
        }
      }
    }
