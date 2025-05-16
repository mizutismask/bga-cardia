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
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */

declare(strict_types=1);

namespace Bga\Games\Cardia;

use Deck;
use Bga\Games\Cardia\objects\CardiaCard;
use Bga\Games\Cardia\objects\Faction;
use Bga\Games\Cardia\objects\InteractionType;

//require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");
require_once("constants.inc.php");

class Game extends \Bga\GameFramework\Table {
    use UtilTrait;
    use PlayerUtilTrait;
    use DBUtilTrait;
    use GameUtilTrait;
    use ActionTrait;
    use StateTrait;
    use ArgsTrait;
    use DebugUtilTrait;
    use ExpansionTrait;
    use ContextTrait;

    private Deck $cards;
    private Deck $tokens;
    private CardManager $cardManager;
    private TokenManager $tokenManager;
    public $CARDIA_CARDS;
    public $LOCATIONS;

    function __construct() {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();

        $this->initGameStateLabels(array(
            LAST_TURN => 10, // last turn is the id of the last player, 0 if it's not last turn
            //    "my_second_global_variable" => 11,
            //      ...
            //    "my_first_game_variant" => 100,
            //    "my_second_game_variant" => 101,
            //      ...
        ));
        $this->cards = $this->getNew("module.common.deck");
        $this->cards->init("card");
        $this->cardManager = new CardManager($this, TABLE_CARD, $this->cards, "CardiaCard", MATERIAL_TYPE_CARD, [
            "material" => $this->CARDIA_CARDS,
            "deck" => $this->refreshGlobalValue(101),
            "location" => $this->refreshGlobalValue(102)
        ]); //get deck from table options

        $this->tokens = $this->getNew("module.common.deck");
        $this->tokens->init("token");
        $this->tokenManager = new TokenManager($this, TABLE_TOKEN, $this->tokens, "CardiaToken", MATERIAL_TYPE_TOKEN);
    }

    protected function getGameName() {
        // Used for translations and stuff. Please do not modify.
        return "cardia";
    }

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame($players, $options = []) {
        // Set the colors of the players with HTML color code. The default below is red/green/blue/orange/brown. The
        // number of colors defined here must correspond to the maximum number of players allowed for the gams.
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        foreach ($players as $player_id => $player) {
            // Now you can access both $player_id and $player array
            $query_values[] = vsprintf("('%s', '%s', '%s', '%s', '%s')", [
                $player_id,
                array_shift($default_colors),
                $player["player_canal"],
                addslashes($player["player_name"]),
                addslashes($player["player_avatar"]),
            ]);
        }

        // Create players based on generic information.
        //
        // NOTE: You can add extra field on player table in the database (see dbmodel.sql) and initialize
        // additional fields directly here.
        static::DbQuery(
            sprintf(
                "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES %s",
                implode(",", $query_values)
            )
        );
        $this->reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        $this->reloadPlayersBasicInfos();

        /************ Start the game initialization *****/

        // Init global values with their initial values
        //$this->setGameStateInitialValue( 'my_first_global_variable', 0 );
        //initialize everything to be compliant with undo framework
        //foreach ($this->GAMESTATELABELS as $value_label => $ID) if ($ID >= 10 && $ID < 90) $this->setGameStateInitialValue($value_label, 0);

        $this->initStats();

        // TODO: setup the initial game situation here
        $this->setupTable($players);

        /************ End of the game initialization *****/
    }

    function setupTable($players) {
        $this->setupSharedItems();
        $this->cardManager->dealHands();
        $this->globals->set(GLB_DUEL_COUNT, 1);
        $this->globals->set(GLB_ROUND, 1);
        $this->globals->set(GLB_SERPENT_TEMPLE_DISCARDERS, []);
        foreach ($players as $playerId => $player) {
        }
    }

    function setupSharedItems() {
        $this->cardManager->createCards($this->getCardsToGenerate());
        $this->tokenManager->createCards($this->getTokensToGenerate());
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas() {
        $stateName = $this->getStateName();
        $isEnd = $stateName === 'endScore' || $stateName === 'gameEnd' || $stateName === 'debugGameEnd';

        $result = [];

        $currentPlayerId = $this->getCurrentPlayerId();    // !! We must only return informations visible by this player !!

        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score, player_no playerNo FROM player ";
        $result['players'] = $this->getCollectionFromDb($sql);
        $result['playerOrderWorkingWithSpectators'] = $this->getPlayerIdsInOrder($currentPlayerId);
        $result['turnOrderClockwise'] = true;
        $result['version'] = $this->getGameVersion();
        $result['counters'] = $this->argCounters();
        $result['duels'] = $this->cardManager->getVisibleDuelsList(intval($currentPlayerId));
        $result['signets'] = $this->tokenManager->getSignetsOnCards();
        $result['ongoingTokens'] = $this->tokenManager->getOngoingTokensOnCards();
        $result['modifiers'] = $this->cardManager->getModifiers();
        if ($this->getStateName() == "chooseDuelCard" && !$this->gamestate->isPlayerActive($currentPlayerId)) {
            $result['lastChosenCard'] = json_decode($this->globals->get(GLB_LAST_CHOSEN_CARD . "_" . $currentPlayerId, ""));
        }

        foreach ($result['players'] as $playerId => &$player) {
            $currentPlayerOrder = intval($player['playerNo']);
            $player['playerNo'] = $currentPlayerOrder;
            $player['discard'] = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $currentPlayerOrder, MATERIAL_LOCATION_DISCARD);
            $player['hand'] = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $currentPlayerOrder, MATERIAL_LOCATION_HAND);
            $player['signetCount'] = $this->tokenManager->getSignetCount($currentPlayerOrder);
            $player['nextCardModifier'] = $this->globals->get(GLB_NEXT_CARD_MODIFIER . $playerId);
        }

        // TODO: Gather all information about current game situation (visible by player $current_player_id).
        $result['expansion'] = $this->getDeck();
        $result['location'] = $this->getScenery();
        $result['locationOptions'] = $this->getTableOptions()[102]["values"];
        if ($isEnd) {
            $maxScore = max(array_map(fn($player) => intval($player['score']), $result['players']));
            $result['winners'] = array_keys(array_filter($result['players'], fn($player) => intval($player['score'] == $maxScore)));
            if (count($result['winners']) > 1) {
                $tieWinners =  array_filter($result['players'], fn($player) => in_array($player["id"], $result['winners']));
                $maxScore = max(array_map(fn($player) => intval($player['scoreAux']), $tieWinners));
                $result['winners'] = array_keys(array_filter($tieWinners, fn($player) => intval($player['scoreAux'] == $maxScore)));
            }
        } else {
            $result['lastTurn'] = $this->getGameStateValue(LAST_TURN) > 0;
        }
        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression() {
        $stateName = $this->getStateName();
        if ($stateName === 'endScore' || $stateName === 'gameEnd') {
            // game is over
            return 100;
        }

        $signetCounts = [];
        foreach ($this->getPlayers() as $playerId => $player) {
            $signetCounts[$playerId] = $this->tokenManager->getSignetCount(intval($player["player_no"]));
        }
        $maxSignets = min(5, max($signetCounts));
        $duelProgression  = 0;
        if ($stateName != "seeEndOfRound") {
            $duelProgression  =  100 * ($maxSignets) / 5;
        }

        //$this->dump('******************maxSignets*', $maxSignets);
        //$this->dump('******************duelProgression*', $duelProgression);

        $round = intval($this->globals->get(GLB_ROUND));
        return (100 * $this->getMaxScore() / 2) + $duelProgression / ($round == 3 ? 3 : 2);
    }

    function getGameVersion(): int {
        return intval($this->gamestate->table_globals[300]);
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////    
    function makeSavepoint($player_id = null) {
        $this->undoSavepoint();
    }

    function toggleResetTurn($value) {
        $this->globals->set(CAN_RESET_TURN, $value);
    }
    /*
        In this space, you can put any utility methods useful for your game logic
    */

    //////////////////////////////////////////////////////////////////////////////
    //////////// Zombie
    ////////////

    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, otherwise it will fail with a
     * "Not logged" error message.
     * 
     * Plays the first card in hand if needed, or randomly selects a faction
     *
     * @param array{ type: string, name: string } $state
     * @param int $active_player
     * @return void
     * @throws feException if the zombie mode is not supported at this game state.
     */
    function zombieTurn(array $state, int $active_player): void {
        $statename = $state['name'];

        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                case 'librarianAbility':
                    $possibleValues = [2, -2];
                    $this->chooseLibrarianModifier($active_player, $this->getRandomValue($possibleValues));
                    break;
                case 'blackmailerDiscard':
                    $cards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $this->getPlayerPosition($active_player), MATERIAL_LOCATION_HAND);
                    $this->blackmailerDiscard($this->getRandomSlice($cards, min(2, count($cards))));
                    break;
                case 'serpentTempleDiscard':
                    $cards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $this->getPlayerPosition($active_player), MATERIAL_LOCATION_HAND);
                    $this->applySerpentTemple($active_player, array_shift($cards));
                    break;
                case 'chooseFortuneTellerCard':
                    $cards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $this->getPlayerPosition($active_player), MATERIAL_LOCATION_HAND);
                    $this->chooseDuelCard($active_player, array_shift($cards));
                    break;
                case 'interactiveAbility':
                    $args = $this->argInteractiveAbility();
                    $faction = null;
                    $cards = [];
                    $possibleOptions = ['removeModifiers', 'removeOngoingToken'];
                    $option = $this->getRandomValue($possibleOptions); //for void mage
                    if ($args['interactionType'] == InteractionType::selectFaction) {
                        $possibleFactions = Faction::cases();
                        $faction =  $this->getRandomValue($possibleFactions);
                        $this->globals->set(GLB_SELECTED_FACTION, $faction->value);
                    } else {
                        $cards = $this->getRandomSlice($args["selectableCards"], $args["qty"]);
                    }
                    $this->applyInteractiveAbility($args['abilityCard'], $faction, $cards, $option);
                    break;
                case 'interactiveAbilityStep2':
                    $args = $this->argInteractiveAbilityStep2();
                    $card = null;
                    if ($args['interactionType'] != InteractionType::selectFaction) {
                        $card = array_shift($args["selectableCards"]);
                    }
                    $this->applyInteractiveAbilityStep2($args['abilityCard'], $card);
                    break;
                default:
                    throw new \feException("Zombie mode not supported at this game state: " . $statename);
                    break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            switch ($statename) {
                case 'chooseDuelCard':
                    $cards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $this->getPlayerPosition($active_player), MATERIAL_LOCATION_HAND);
                    $this->chooseDuelCard($active_player, array_shift($cards));
                    break;
                case 'scrapyardChooseCard':
                    $cards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $this->getPlayerPosition($active_player), MATERIAL_LOCATION_HAND);
                    $this->chooseScrapyardCard($active_player, array_shift($cards));
                    break;

                default:
                    // Make sure player is in a non blocking status for role turn
                    $this->gamestate->setPlayerNonMultiactive($active_player, '');
                    break;
            }


            return;
        }

        throw new \feException("Zombie mode not supported at this game state: " . $statename);
    }

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// DB upgrade
    //////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */

    function upgradeTableDb($from_version) {
        $changes = [
            // [2307071828, "INSERT INTO DBPREFIX_global (`global_id`, `global_value`) VALUES (24, 0)"], 
        ];

        foreach ($changes as [$version, $sql]) {
            if ($from_version <= $version) {
                try {
                    $this->warn("upgradeTableDb apply 1: from_version=$from_version, change=[ $version, $sql ]");
                    $this->applyDbUpgradeToAllDB($sql);
                } catch (\Exception $e) {
                    // See https://studio.boardgamearena.com/bug?id=64
                    // BGA framework can produce invalid SQL with non-existant tables when using DBPREFIX_.
                    // The workaround is to retry the query on the base table only.
                    $this->error("upgradeTableDb apply 1 failed: from_version=$from_version, change=[ $version, $sql ]");
                    $sql = str_replace("DBPREFIX_", "", $sql);
                    $this->warn("upgradeTableDb apply 2: from_version=$from_version, change=[ $version, $sql ]");
                    $this->applyDbUpgradeToAllDB($sql);
                }
            }
        }
        $this->warn("upgradeTableDb complete: from_version=$from_version");
    }
}
