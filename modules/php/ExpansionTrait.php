<?php

namespace Bga\Games\Cardia;

use Bga\Games\cardia\objects\TokenType;

trait ExpansionTrait {

    function getDeck() {
        return $this->tableOptions->get(101);
    }

    function getScenery() {
        return $this->tableOptions->get(102);
    }

    function getCardsToGenerate() {
        $cards = [];
        $expansion = $this->getDeck();

        foreach ($this->CARDIA_CARDS[$expansion] as $typeArg => $card) {
            $cards[] = ['type' => $typeArg, 'type_arg' => 1, 'nbr' => 1];
            $cards[] = ['type' => $typeArg, 'type_arg' => 2, 'nbr' => 1];
        }

        return $cards;
    }

    function getTokensToGenerate() {
        $tokens = [];

        $tokens[] = ['type' => TokenType::SIGIL->value, 'type_arg' => 0, 'nbr' => 11];
        $tokens[] = ['type' => TokenType::ONGOING->value, 'type_arg' => 0, 'nbr' => 6];

        return $tokens;
    }
}
