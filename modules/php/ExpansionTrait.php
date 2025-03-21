<?php

namespace Bga\Games\cardia;

trait ExpansionTrait {

    function getExpansion() {
        // return $this->isExpertMode() () ? CAT_DOG : EXPANSION;
    }

    function getCardsToGenerate() {
        $cards = [];
        $expansion = $this->getExpansion();

        switch ($expansion) {
            default:
                foreach ($this->DESTINATIONS[1] as $typeArg => $destination) {
                    if ($typeArg != 0) { //starting point is excluded
                        $cards[] = ['type' => 1, 'type_arg' => $typeArg, 'nbr' => 1];
                    }
                }
                break;
        }

        return $cards;
    }

    /**
     * Return the number of destinations cards shown at the beginning.
     */
    function getInitialDestinationCardNumber(): int {
        $playerCount = $this->getPlayerCount();
        switch ($this->getExpansion()) {
            default:
                if ($playerCount == 2 || $playerCount == 3)
                    return 12;
                return 9;
        }
    }
}
