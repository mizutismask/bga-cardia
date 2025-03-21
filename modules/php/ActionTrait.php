<?php

namespace Bga\Games\Cardia;

use \Bga\GameFramework\Actions\Types\IntArrayParam;
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

    /**
     * @param int[] $cardIds
     */
    function actSelectInSet(int $version, #[IntArrayParam] array $cardIds) {
        $this->checkVersion($version);
        $this->checkAction('actSelectInSet');
        $playerId = $this->getMostlyActivePlayerId();
        $playerOrder = $this->getPlayerPosition($playerId);

        /*$this->orderDeck($cardIds, false, 'actSelectInSet', MATERIAL_LOCATION_SET);

        $this->notifyPlayer($playerId, "materialMove", '', [
            'type' => MATERIAL_TYPE_CARD,
            'from' => MATERIAL_LOCATION_SET,
            'fromArg' => $playerId,
            'to' => MATERIAL_LOCATION_DECK,
            'toArg' => $playerId,
            'material' => $this->cardManager->getCards($cardIds),
        ]);
        $this->cardManager->setWaitingCard($playerId, $playerOrder);*/
        $this->gamestate->setPlayerNonMultiactive($playerId, '');
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
