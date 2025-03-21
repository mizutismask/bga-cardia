<?php

use Bga\Games\Cardia\objects\CardiaCardInfo;
use Bga\Games\wizardsCup\objects\PowerType;

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Cardia implementation : © Séverine Kamycki <mizutismask@gmail.com>
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * material.inc.php
 *
 * Cardia game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *   
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */


$this->CARDIA_CARDS = [
  1 => [
    //deck 1
    1  => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
    2  => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
    3  => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
    4  => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
    5  => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
    6  => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
    7  => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
    8  => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
    9  => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
    10 => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
    11 => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
    12 => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
    13 => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
    14 => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
    15 => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
    16 => new CardiaCardInfo(3, PowerType::IMMEDIATE, self::_("Dancer")),
  ],
  2 => [
    //deck 2
  ],

];
