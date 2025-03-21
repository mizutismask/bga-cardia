<?php

use Bga\Games\cardia\Game;

define("APP_GAMEMODULE_PATH", "../misc/"); // include path to stubs, which defines "table.game.php" and other classes

abstract class GameTestBase extends Game { // this is your game class defined in ggg.game.php
    function __construct() {
        // parent::__construct();
        include '../material.inc.php'; // this is how this normally included, from constructor
    }

    abstract function testAll();

    /**
     * To redefine if players count is not 3
     */
    function getPlayersIds() {
        return [1, 2, 3];
    }

    /*
    To redefine to mock data.
    */
   /* function getBoard($playerId = null) {
        return $this->initBoard();
    }*/

    function displayResult($testName, $equal, $result) {
        echo ($testName);
        if ($equal) {
            echo " : SUCCESS\n";
        } else {
            echo " : FAILURE\n";
            echo is_array($result) ? "Found: " . json_encode($result) : "Found: $result\n";
        }
    }

    function expectResult($result,  $expectedResult, $testName) {
        $equal = $result == $expectedResult;
        $this->displayResult($testName, $equal, $result);
    }

    function testExemple() {
        //get this typing displayPlayerGrid() in the chat, remove the last number of each line except the last one
       /* $grid = $this->convertNumbersToGrid("
            121641
            355182
            245716
            974779
            258725
            383687
        ");

        //give more info for otters
        /*   $grid[2][3] = new Biome(ANIMAL_OTTER, LAND_JUNGLE, RIVER_DOWN);
        $grid[3][1] = new Biome(ANIMAL_OTTER, LAND_SAVANNAH, RIVER_DOWN);
        $grid[3][3] = new Biome(ANIMAL_OTTER, LAND_SAVANNAH, RIVER_UP);
        $grid[3][4] = new Biome(ANIMAL_OTTER, LAND_JUNGLE, RIVER_DOWN);
        $grid[4][3] = new Biome(ANIMAL_OTTER, LAND_SAVANNAH, RIVER_DOWN);
        $grid[4][3] = new Biome(ANIMAL_OTTER, LAND_JUNGLE, RIVER_DOWN);

        $result = $this->calculateGoalRiverConnectedToLand($grid, LAND_WATER);*/

        //test result
        /*  $equal = $result == 10;
        $this->displayResult(__FUNCTION__, $equal, $result);*/
    }
}
