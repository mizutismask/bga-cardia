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
        "transitions" => ["" => ST_PLAYER_CHOOSE_DUEL_CARD]
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
            "evaluateDuel" => ST_DUEL_EVALUATION,
            "librarianAbility" => ST_PLAYER_LIBRARIAN_ABILITY,
            "blackmailerDiscard" => ST_PLAYER_BLACKMAILER_DISCARD,
        ],
    ],

    ST_DUEL_EVALUATION => [
        "name" => "duelEvaluation",
        "description" => "",
        "type" => "game",
        "action" => "stDuelEvaluation",
        "updateGameProgression" => false,
        "transitions" => [
            "finishDuel" => ST_FINISH_DUEL,
            "looserAbility" => ST_LOOSER_ABILITY,
            "nextRound" => ST_PLAYER_SEE_END_OF_ROUND, //founders day
        ],
    ],

    ST_LOOSER_ABILITY => [
        "name" => "looserAbility",
        "type" => "game",
        "action" => "stLooserAbility",
        "transitions" => [
            "interactiveAbility" => ST_ACTIVATE_PLAYER_FOR_ABILITY,
            "finishDuel" => ST_FINISH_DUEL, //not sure
            "nextRound" => ST_PLAYER_SEE_END_OF_ROUND, //djinn power
        ]
    ],

    ST_ACTIVATE_PLAYER_FOR_ABILITY => [
        "name" => "activatePlayerForAbility",
        "type" => "game",
        "action" => "stActivatePlayerForAbility",
        "transitions" => [
            "interactiveAbilityStep2" => ST_INTERACTIVE_ABILITY_STEP_2,
            "interactiveAbility" => ST_INTERACTIVE_ABILITY,
        ]
    ],

    ST_FINISH_DUEL => [
        "name" => "finishDuel",
        "description" => "",
        "type" => "game",
        "action" => "stFinishDuel",
        "updateGameProgression" => false,
        "transitions" => [
            "nextRound" => ST_PLAYER_SEE_END_OF_ROUND,
            "chooseDuelCard" => ST_PLAYER_CHOOSE_DUEL_CARD,
            "chooseFortuneTellerCard" => ST_PLAYER_CHOOSE_FORTUNE_TELLER_CARD,
            "chooseScrapyardCard" => ST_PLAYER_SCRAPYARD_CHOOSE_CARD,
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
        "descriptionmyturnBlackmailerAbility" => clienttranslate('${you} must play a ${_private.blackmailerFaction} card or face Blackmailer consequences'),
        "type" => "multipleactiveplayer",
        "args" => "argChooseDuelCard",
        'action' => 'stActivatePlayersToChooseDuelCard',
        "possibleactions" => [
            "actChooseDuelCard",
        ],
        "transitions" => [
            "duelReveal" => ST_DUEL_REVEAL,
        ]
    ],

    ST_PLAYER_SEE_END_OF_ROUND => [
        'name' => 'seeEndOfRound',
        'description' => clienttranslate('Other players are watching the end of the round'),
        'descriptionmyturn' => clienttranslate('${you} can pass when you’ve finished watching the end of the round'),
        'type' => 'multipleactiveplayer',
        'action' => 'stMakeEveryoneActive',
        'possibleactions' => ['actPass'],
        'transitions' => [
            'nextRound' => ST_NEXT_ROUND,
        ]
    ],

    ST_PLAYER_SCRAPYARD_CHOOSE_CARD => [
        "name" => "scrapyardChooseCard",
        "description" => clienttranslate('${actplayer} must choose a card to put under the deck'),
        "descriptionmyturn" => clienttranslate('${you} must choose a card to put under the deck'),
        "type" => "multipleactiveplayer",
        'action' => 'stMakeEveryoneActive',
        "possibleactions" => [
            "actScrapyardChooseCard",
        ],
        "transitions" => [
            "nextRound" => ST_PLAYER_SEE_END_OF_ROUND,
            "chooseDuelCard" => ST_PLAYER_CHOOSE_DUEL_CARD,
            "chooseFortuneTellerCard" => ST_PLAYER_CHOOSE_FORTUNE_TELLER_CARD,
        ]
    ],

    ST_PLAYER_BLACKMAILER_DISCARD => [
        "name" => "blackmailerDiscard",
        "description" => clienttranslate('${actplayer} must choose 2 cards to discard'),
        "descriptionmyturn" => clienttranslate('${you} did not play the required faction, so ${you} must choose 2 cards to discard'),
        "type" => "activeplayer",
        "possibleactions" => [
            "actBlackmailerDiscard",
        ],
        "transitions" => [
            "evaluateDuel" => ST_DUEL_EVALUATION,
        ]
    ],

    ST_PLAYER_CHOOSE_FORTUNE_TELLER_CARD => [
        "name" => "chooseFortuneTellerCard",
        "description" => clienttranslate('Fortune teller: ${actplayer} must choose a card for the next encounter'),
        "descriptionmyturn" => clienttranslate('${you} must choose a card for the next encounter'),
        "type" => "activeplayer",
        "possibleactions" => [
            "actChooseDuelCard",
        ],
        "transitions" => [
            "opponentChooseCard" => ST_PLAYER_CHOOSE_DUEL_CARD,
        ]
    ],
    
    ST_PLAYER_LIBRARIAN_ABILITY => [
        "name" => "librarianAbility",
        "description" => clienttranslate('Librarian: ${actplayer} must choose to add +2 or -2 to his card'),
        "descriptionmyturn" => clienttranslate('Librarian: ${you} must choose to add +2 or -2 to your card'),
        "type" => "activeplayer",
        "possibleactions" => [
            "actChooseModifier",
        ],
        "transitions" => [
            "evaluateDuel" => ST_DUEL_EVALUATION,
        ]
    ],

    ST_INTERACTIVE_ABILITY => [
        "name" => "interactiveAbility",
        "type" => "activeplayer",

        "description" => clienttranslate('${actplayer} must resolve his ability'),
        "descriptionmyturn" => clienttranslate('${you} must resolve your ability'),
        "description" . PALACE_GUARD => clienttranslate('${ability} ability: ${actplayer} is choosing a faction'),
        "descriptionmyturn" . PALACE_GUARD => clienttranslate('${ability} ability: Select a faction'),
        "description" . AMBUSHER => clienttranslate('${ability} ability: ${actplayer} is choosing a faction'),
        "descriptionmyturn" . AMBUSHER => clienttranslate('${ability} ability: choose a faction your opponent will have to discard'),
        "description" . INVENTOR => clienttranslate('${ability} ability: ${actplayer} is choosing a card'),
        "descriptionmyturn" . INVENTOR => clienttranslate('${ability} ability: choose a card to set ${influence} influence on it'),
        "description" . VOID_MAGE => clienttranslate('${ability} ability: ${actplayer} is choosing a card'),
        "descriptionmyturn" . VOID_MAGE => clienttranslate('${ability} ability: choose a card to remove its modifiers or its ongoing tokens'),
        "description" . SWAMP_GUARDIAN => clienttranslate('${ability} ability: ${actplayer} is choosing a card'),
        "descriptionmyturn" . SWAMP_GUARDIAN => clienttranslate('${ability} ability: choose a card to take it back in hand'),
        "description" . MAGISTRA => clienttranslate('${ability} ability: ${actplayer} is choosing a card'),
        "descriptionmyturn" . MAGISTRA => clienttranslate('${ability} ability: choose a card to activate its ability'),
        "description" . KINESIS_MAGE => clienttranslate('${ability} ability: ${actplayer} is choosing a card'),
        "descriptionmyturn" . KINESIS_MAGE => clienttranslate('${ability} ability: choose the source card to move all tokens and modifiers from'),
        "description" . PRODIGY => clienttranslate('${ability} ability: ${actplayer} is choosing a card'),
        "descriptionmyturn" . PRODIGY => clienttranslate('${ability} ability: choose a card with 8 or less influence to add +3 influence to it'),
        "description" . ENVOY => clienttranslate('${ability} ability: ${actplayer} is choosing a card'),
        "descriptionmyturn" . ENVOY => clienttranslate('${ability} ability: choose a card to add -3 influence to it, or none to add it to your next card'),
        "description" . REVOLUTIONARY => clienttranslate('${ability} ability: ${actplayer} is choosing a card'),
        "descriptionmyturn" . REVOLUTIONARY => clienttranslate('${ability} ability: choose 2 cards to discard from your hand'),
        "description" . SUCCESSOR => clienttranslate('${ability} ability: ${actplayer} is choosing cards'),
        "descriptionmyturn" . SUCCESSOR => clienttranslate('${ability} ability: choose 2 cards from your hand to keep'),
        "description" . WITCH_KING => clienttranslate('${ability} ability: ${actplayer} is choosing a faction'),
        "descriptionmyturn" . WITCH_KING => clienttranslate('${ability} ability: Select a faction'),
        "description" . BLACKMAILER => clienttranslate('${ability} ability: ${actplayer} is choosing a faction'),
        "descriptionmyturn" . BLACKMAILER => clienttranslate('${ability} ability: Select a faction'),
        "description" . ILLUSIONIST => clienttranslate('${ability} ability: ${actplayer} is choosing a card'),
        "descriptionmyturn" . ILLUSIONIST => clienttranslate('${ability} ability: choose one of your loosing cards to activate its ability'),
        "description" . ELEMENTAL => clienttranslate('${ability} ability: ${actplayer} is choosing a card'),
        "descriptionmyturn" . ELEMENTAL => clienttranslate('${ability} ability: choose a card in your hand to copy its ability'),

        "args" => "argInteractiveAbility",
        "possibleactions" => [
            "actInteractiveAbility",
        ],
        "transitions" => [
            "interactiveAbilityStep2" => ST_ACTIVATE_PLAYER_FOR_ABILITY,
            "interactiveAbility" => ST_ACTIVATE_PLAYER_FOR_ABILITY, //magistra
            "finishDuel" => ST_FINISH_DUEL,
            "nextRound" => ST_PLAYER_SEE_END_OF_ROUND, //djinn power copied with magistra
        ]
    ],

    ST_INTERACTIVE_ABILITY_STEP_2 => [
        "name" => "interactiveAbilityStep2",

        "description" => clienttranslate('${actplayer} must resolve his ability'),
        "descriptionmyturn" => clienttranslate('${you}  must resolve your ability'),
        "description" . PALACE_GUARD => clienttranslate('${ability} ability: ${actplayer} may discard a ${faction} card to prevent +7 influence on your card'),
        "descriptionmyturn" . PALACE_GUARD => clienttranslate('${ability} ability: ${you} may discard a ${faction} card to prevent +7 influence on your opponent’s card'),
        "description" . INVENTOR => clienttranslate('${ability} ability: ${actplayer} is choosing a card'),
        "descriptionmyturn" . INVENTOR => clienttranslate('${ability} ability: choose a card to set ${influence} influence on it'),
        "description" . KINESIS_MAGE => clienttranslate('${ability} ability: ${actplayer} is choosing a card'),
        "descriptionmyturn" . KINESIS_MAGE => clienttranslate('${ability} ability: choose the destination card to put all the moved tokens and modifiers on'),

        "type" => "activeplayer",
        "args" => "argInteractiveAbilityStep2",
        "possibleactions" => [
            "actInteractiveAbilityStep2",
        ],
        "transitions" => [
            "finishDuel" => ST_FINISH_DUEL,
        ]
    ],
];

$gameGameStates = [

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
