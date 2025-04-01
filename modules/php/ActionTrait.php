<?php

namespace Bga\Games\Cardia;

use \Bga\GameFramework\Actions\Types\IntArrayParam;
use \Bga\GameFramework\Actions\Types\StringParam;

use Bga\Games\Cardia\objects\CardiaCard;
use Bga\Games\Cardia\objects\Faction;
use GameState;
use Globals;

/**
 * @property CardManager cardManager
 * @property GameState gamestate
 * @property Globals globals
 */
trait ActionTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 
    function actChooseDuelCard(int $version, int $cardId) {
        $this->checkVersion($version);
        $this->checkAction('actChooseDuelCard');
        $playerId = $this->getMostlyActivePlayerId();
        $card = $this->cardManager->getCard($cardId);
        $this->userAssertTrue($this->_("This card is not in your hand"), $card->location == "hand" && $card->location_arg == $playerId);

        $this->chooseDuelCard($this->getMostlyActivePlayerId(), $card);
    }

    function chooseDuelCard(int $playerId, CardiaCard $card) {
        $this->globals->set(GLB_LAST_CHOSEN_CARD . "_" . $playerId, json_encode($card));
        $this->gamestate->setPlayerNonMultiactive($playerId, '');
    }

    function actInteractiveAbility(int $version, #[StringParam(enum: ['G', 'R', 'Y', 'B'])] $faction, ?int $cardId) {
        $this->checkVersion($version);
        $this->checkAction('actInteractiveAbility');
        $playerId = $this->getMostlyActivePlayerId();
        $interactiveAbility = $this->cardManager->getCard($this->globals->get(GLB_ABILITY_TO_RESOLVE));
        $interactionType = $this->getInteractionType($interactiveAbility);
        if ($interactionType == "selectFaction") {
            $this->userAssertTrue($this->_("You have to select a faction"), $interactiveAbility &&  $faction);
            $this->globals->set(GLB_SELECTED_FACTION, $faction);
        } else if ($interactionType == "selectCard") {
            $card = $this->cardManager->getCard($cardId);
            $this->userAssertTrue($this->_("You have to select a card"), $cardId && $card);
            $this->globals->set(GLB_SELECTED_CARD_ID, $cardId);
        }

        $this->applyInteractiveAbility($interactiveAbility, Faction::tryFrom($faction), $card);
    }

    /**
     * @throws BgaUserException
     */
    function actPass(int $version) {
        $this->checkVersion($version);
        $this->checkAction('actPass');

        $args = $this->argChooseAction();

        if (!$args['canPass']) {
            throw new \BgaUserException($this->_("You cannot pass"));
        }

        //$this->dbInsertContextLog(ACTION_PLAY_TICKET, $festivalId, $slotId);
        //$this->changeNextStateFromContext();

        $this->gamestate->nextState('nextPlayer');
    }

    /** Undo all the player turn actions. 
     * @throws BgaUserException
     */
    function actResetPlayerTurn(int $version) {
        $this->checkVersion($version);
        $possible = $this->globals->get(CAN_RESET_TURN);
        if (!$possible) {
            throw new \BgaUserException(self::_("Undo is not available"));
        }
        $this->undoRestorePoint();
        $this->toggleResetTurn(false);
        $this->gamestate->reloadState();
    }
}
