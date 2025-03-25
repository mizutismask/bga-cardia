<?php

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Cardia implementation : © Séverine Kamycki <mizutismask@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * states.inc.php
 *
 * Cardia game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: $this->checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!
require_once("modules/php/constants.inc.php");

$basicGameStates = [

    // The initial state. Please do not modify.
    ST_BGA_GAME_SETUP => [
        "name" => "gameSetup",
        "description" => clienttranslate("Game setup"),
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => ["" => ST_DEAL_INITIAL_SETUP]
    ],

    ST_DEBUG_END_GAME => [
        "name" => "debugGameEnd",
        "description" => "Debug end of game",
        "type" => "manager",
        "args" => "argGameEnd",
        "transitions" => ["endGame" => ST_END_GAME],
    ],

    ST_DUEL_REVEAL => [
        "name" => "duelReveal",
        "description" => "",
        "type" => "game",
        "action" => "stDuelReveal",
        "updateGameProgression" => false,
        "transitions" => [
            "finishDuel" => ST_FINISH_DUEL,
            "looserAbility" => ST_LOOSER_ABILITY,
        ],
    ],

    ST_FINISH_DUEL => [
        "name" => "finishDuel",
        "description" => "",
        "type" => "game",
        "action" => "stFinishDuel",
        "updateGameProgression" => false,
        "transitions" => [
            "nextRound" => ST_NEXT_ROUND,
            "chooseDuelCard" => ST_PLAYER_CHOOSE_DUEL_CARD,
        ],
    ],


    // Final state.
    // Please do not modify.
    ST_END_GAME => [
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd",
    ],
];

$playerActionsGameStates = [

    ST_PLAYER_CHOOSE_DUEL_CARD => [
        "name" => "chooseDuelCard",
        "description" => clienttranslate('${actplayer} must choose a card for the next encounter'),
        "descriptionmyturn" => clienttranslate('${you} must choose a card for the next encounter'),
        "type" => "multipleactiveplayer",
        //"args" => "argChooseDuelCard",
        'action' => 'stMakeEveryoneActive',
        "possibleactions" => [
            "actChooseDuelCard",
        ],
        "transitions" => [
            "duelReveal" => ST_DUEL_REVEAL,
        ]
    ],

    ST_LOOSER_ABILITY => [
        "name" => "looserAbility",
        "description" => clienttranslate('${actplayer} must resolve his ability'),
        "descriptionmyturn" => clienttranslate('${you}  must resolve your ability'),
        "type" => "activeplayer",
        //"args" => "argChooseDuelCard",
        "possibleactions" => [
            //"actLooserAbility",
        ],
        "transitions" => [
            "finishDuel" => ST_FINISH_DUEL,
        ]
    ],
];

$gameGameStates = [
    ST_DEAL_INITIAL_SETUP => [
        "name" => "dealInitialSetup",
        "description" => "",
        "type" => "game",
        "action" => "stDealInitialSetup",
        "transitions" => [
            "" => ST_NEXT_PLAYER,
        ],
    ],

    ST_NEXT_PLAYER => [
        "name" => "nextPlayer",
        "description" => "",
        "type" => "game",
        "action" => "stNextPlayer",
        "updateGameProgression" => true,
        "transitions" => [
            "nextPlayer" => ST_PLAYER_CHOOSE_DUEL_CARD,
            "endScore" => ST_END_SCORE,
        ],
    ],

    ST_NEXT_ROUND => [
        "name" => "nextRound",
        "description" => "",
        "type" => "game",
        "action" => "stNextRound",
        "updateGameProgression" => true,
        "transitions" => [
            "chooseDuelCard" => ST_PLAYER_CHOOSE_DUEL_CARD,
            'endGame' => ST_END_GAME,
            "debugEndGame" => ST_DEBUG_END_GAME,
        ],
    ],

    ST_END_SCORE => [
        "name" => "endScore",
        "description" => "",
        "type" => "game",
        "action" => "stEndScore",
        "transitions" => [
            "endGame" => ST_END_GAME,
            "debugEndGame" => ST_DEBUG_END_GAME,
        ],
    ],
];

$machinestates = $basicGameStates + $playerActionsGameStates + $gameGameStates;
