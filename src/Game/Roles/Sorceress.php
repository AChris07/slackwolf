<?php namespace Slackwolf\Game\Roles;

use Slackwolf\Game\Role;

/**
 * Defines the Sorceress class.
 *
 * @package Slackwolf\Game\Roles
 */
class Sorceress extends Role
{

    /**
     * {@inheritdoc}
     */
	public function getName() {
		return Role::SORCERESS;
	}

    /**
     * {@inheritdoc}
     */
	public function getDescription() {
		return "A player on the side of the Werewolves. She does not get to know who the werewolves are, or viceversa. Once per night, is allowed to see if a player is the Seer or not.";
	}
}