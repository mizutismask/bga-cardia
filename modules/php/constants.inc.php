<?php

/*
 * BGA constants 
 */
const GLB_LAST_CHOSEN_CARD = 'lastChosenCard';
const GLB_DUEL_COUNT = 'duelCount';
const GLBL_ROUND = 'round';
const GLB_SELECTED_FACTION = 'selectedFaction';
const GLB_SELECTED_CARD_ID = 'selectedCardId';
const GLB_ABILITY_TO_RESOLVE = 'abilityToResolve';
const GLB_PLAYER_TO_ACTIVATE = 'playerToActivate';
const GLB_INVENTOR_PLUS_CARD = 'inventorPlusCard';
const GLB_NEXT_CARD_MODIFIER = 'nextCardModifier';
const GLB_NEXT_CARD_MODIFIER_AFTER_ABILITY_TRIGGERED = 'nextCardModifierAfterAbility';
const GLB_STEP_2 = 'step2';
const GLB_NEXT_STATE_AFTER_SCRAPYARD = 'nextStateAfterScrapyard';
define("GS_PLAYER_TURN_NUMBER", 'playerturn_nbr');

/*
 * Custom framework constants
 */
const MATERIAL_TYPE_CARD = "CARD";
const MATERIAL_TYPE_TOKEN = "TOKEN";
const MATERIAL_TYPE_ONGOING_TOKEN = "ONGOING_TOKEN";
const MATERIAL_TYPE_ACTION_CARD = "ACTION_CARD";
const MATERIAL_TYPE_FIRST_PLAYER_TOKEN = "FIRST_PLAYER_TOKEN";

const MATERIAL_LOCATION_HAND = "hand";
const MATERIAL_LOCATION_DECK = "deck";
const MATERIAL_LOCATION_CARD = "card";
const MATERIAL_LOCATION_STOCK = "stock";
const MATERIAL_LOCATION_DISCARD = "discard";
const MATERIAL_LOCATION_RIVER = "river";
const MATERIAL_LOCATION_ENCOUNTER = "encounter";

/* 
 * Game constants 
 */
const CAN_RESET_TURN = "CAN_RESET_TURN";

/**
 * Options
 */
define('EXPANSION', 0); // 0 => base game

/*
 * State constants
 */
define('ST_BGA_GAME_SETUP', 1);
define('ST_DEAL_INITIAL_SETUP', 10);

define('ST_PLAYER_CHOOSE_DUEL_CARD', 30);
define('ST_DUEL_REVEAL', 31);
define('ST_FINISH_DUEL', 32);
define('ST_LOOSER_ABILITY', 33);
define('ST_NEXT_ROUND', 34);
define('ST_INTERACTIVE_ABILITY', 35);
define('ST_INTERACTIVE_ABILITY_STEP_2', 36);
define('ST_ACTIVATE_PLAYER_FOR_ABILITY', 37);
define('ST_PLAYER_CHOOSE_FORTUNE_TELLER_CARD', 38);
define('ST_PLAYER_SCRAPYARD_CHOOSE_CARD', 39);

define('ST_NEXT_PLAYER', 80);
define('ST_NEXT_REVEAL', 81);

define('ST_DEBUG_END_GAME', 97);
define('ST_END_SCORE', 98);

define('ST_END_GAME', 99);
define('END_SCORE', 100);


/*
 * Variables (numbers)
 */

const LAST_TURN = 'LAST_TURN';


/*
 * Global variables (objects)
 */
//define('LAST_BLUE_ROUTES', 'LAST_BLUE_ROUTES'); //array of the 3 last arrows

/*
    Stats
*/
//define('STAT_POINTS_WITH_PLAYER_COMPLETED_DESTINATIONS', 'pointsWithPlayerCompletedDestinations');


//cards
const HIRED_BLADE = 101;
const VOID_MAGE = 102;
const SURGEON = 103;
const MEDIATOR = 104;
const SABOTEUR = 105;
const FORTUNE_TELLER = 106;
const PALACE_GUARD = 107;
const JUDGE = 108;
const AMBUSHER = 109;
const PUPPETEER = 110;
const CLOCKMAKER = 111;
const TREASURER = 112;
const SWAMP_GUARDIAN = 113;
const MAGISTRA = 114;
const INVENTOR = 115;
const DJINN = 116;

const POISONER = 201;
const KINESIS_MAGE = 202;
const ENVOY = 203;
const TAX_COLLECTOR = 204;
const REVOLUTIONARY = 205;
const LIBRARIAN = 206;
const PRODIGY = 207;
const ARISTOCRAT = 208;
const BLACKMAILER = 209;
const ILLUSIONIST = 210;
const ENGINEER = 211;
const COUNSELOR = 212;
const WITCH_KING = 213;
const ELEMENTAL = 214;
const MECHANICAL_DJINN = 215;
const SUCCESSOR = 216;

const SERPENT_TEMPLE = 1;
const BAZAAR = 2;
const FOUNDERS_DAY = 3;
const GRAND_LIBRARY = 4;
const SCRAPYARD = 5;
const AUCTION_HOUSE = 6;
const HAUNTED_CATACOMBS = 7;
const FOGGY_SWAMP = 8;
