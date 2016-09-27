<?php namespace Slackwolf\Game\Command;

use Exception;
use Slack\ChannelInterface;
use Slack\DirectMessageChannel;
use Slack\RealTimeClient;
use Slackwolf\Game\Formatter\ChannelIdFormatter;
use Slackwolf\Game\Formatter\UserIdFormatter;
use Slackwolf\Game\GameManager;
use Slackwolf\Game\GameState;
use Slackwolf\Game\Role;
use Slackwolf\Message\Message;
use Zend\Loader\Exception\InvalidArgumentException;

/**
 * Defines the ObserveCommand class.
 */
class ObserveCommand extends Command
{

    /**
     * @var string
     */
    private $gameId;

    /**
     * @var string
     */
    private $chosenUserId;

    /**
     * {@inheritdoc}
     *
     * Constructs a new Observe command.
     */
    public function __construct(RealTimeClient $client, GameManager $gameManager, Message $message, array $args = null)
    {
        parent::__construct($client, $gameManager, $message, $args);

        if ($this->channel[0] != 'D') {
            throw new Exception("You may only !observe from a DM.");
        }

        if (count($this->args) < 2) {
            $this->client->getDMById($this->channel)
                         ->then(
                             function (DirectMessageChannel $dmc) use ($client) {
                                 $this->client->send(":warning: Not enough arguments. Usage: !observe #channel @user", $dmc);
                             }
                         );

            throw new InvalidArgumentException();
        }

        $channelId   = null;
        $channelName = "";

        if (strpos($this->args[0], '#C') !== false) {
            $channelId = ChannelIdFormatter::format($this->args[0]);
        } else {
            if (strpos($this->args[0], '#') !== false) {
                $channelName = substr($this->args[0], 1);
            } else {
                $channelName = $this->args[0];
            }
        }

        if ($channelId != null) {
            $this->client->getChannelById($channelId)
                         ->then(
                             function (ChannelInterface $channel) use (&$channelId) {
                                 $channelId = $channel->getId();
                             },
                             function (Exception $e) {
                                 // Do nothing
                             }
                         );
        }

        if ($channelId == null) {
            $this->client->getGroupByName($channelName)
                         ->then(
                             function (ChannelInterface $channel) use (&$channelId) {
                                 $channelId = $channel->getId();
                             },
                             function (Exception $e) {
                                 // Do nothing
                             }
                         );
        }

        if ($channelId == null) {
            $this->client->getDMById($this->channel)
                         ->then(
                             function (DirectMessageChannel $dmc) use ($client) {
                                 $this->client->send(":warning: Invalid channel specified. Usage: !observe #channel @user", $dmc);
                             }
                         );
            throw new InvalidArgumentException();
        }

        $this->game = $this->gameManager->getGame($channelId);
        $this->gameId = $channelId;

        if (!$this->game) {
            $this->client->getDMById($this->channel)
                         ->then(
                             function (DirectMessageChannel $dmc) use ($client) {
                                 $this->client->send(":warning: Could not find a running game on the specified channel.", $dmc);
                             }
                         );

            throw new InvalidArgumentException();
        }

        $this->args[1] = UserIdFormatter::format($this->args[1], $this->game->getOriginalPlayers());
        $this->chosenUserId = $this->args[1];

        $player = $this->game->getPlayerById($this->userId);

        if ( ! $player) {
            $this->client->getDMById($this->channel)
                 ->then(
                     function (DirectMessageChannel $dmc) use ($client) {
                         $this->client->send(":warning: Could not find you in the game you specified.", $dmc);
                     }
                 );

            throw new InvalidArgumentException();
        }

        // Player should be alive
        if ( ! $this->game->isPlayerAlive($this->userId)) {
            $client->getChannelGroupOrDMByID($this->channel)
                ->then(function (ChannelInterface $channel) use ($client) {
                    $client->send(":warning: You aren't alive in the specified channel.", $channel);
                });
            throw new Exception("Can't Observe if dead.");
        }

        if (!$player->role->isRole(Role::SORCERESS)) {
            $this->client->getDMById($this->channel)
                 ->then(
                     function (DirectMessageChannel $dmc) use ($client) {
                         $this->client->send(":warning: You aren't a sorceress in the specified game.", $dmc);
                     }
                 );
            throw new Exception("Player is not the sorceress but is trying to observe.");
        }

        if (! in_array($this->game->getState(), [GameState::FIRST_NIGHT, GameState::NIGHT])) {
            throw new Exception("Can only Observe at night.");
        }

        if ($this->game->hasSorceressObserved()) {
            $this->client->getDMById($this->channel)
                 ->then(
                     function (DirectMessageChannel $dmc) use ($client) {
                         $this->client->send(":warning: You may only observe once each night.", $dmc);
                     }
                 );
            throw new Exception("You may only observe once each night.");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fire()
    {
        $client = $this->client;

        foreach ($this->game->getLivingPlayers() as $player) {
            if (! strstr($this->chosenUserId, $player->getId())) {
                continue;
            }

            if ($player->role->isRole(Role::SEER)) {
                $msg = "@{$player->getUsername()} is the Seer.";
            } else {
                $msg = "@{$player->getUsername()} is not the Seer.";
            }

            $this->client->getDMById($this->channel)
                 ->then(
                     function (DirectMessageChannel $dmc) use ($client, $msg) {
                         $this->client->send($msg, $dmc);
                     }
                 );

            $this->game->setSorceressObserved(true);

            $this->gameManager->changeGameState($this->game->getId(), GameState::DAY);

            return;
        }

        $this->client->getDMById($this->channel)
             ->then(
                 function (DirectMessageChannel $dmc) use ($client) {
                     $this->client->send("Could not find the user you asked for.", $dmc);
                 }
             );
    }
}