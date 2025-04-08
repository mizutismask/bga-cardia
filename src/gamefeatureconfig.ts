/**
 * Enable/Disable default features changing here boolean values.
 * Those are read only since they can’t be modified during the game.
 */
class GameFeatureConfig {
    constructor() {}

    /** Adds the spy icon in other players miniboard. */
    private _spyOnOtherPlayerBoard: boolean = false;

    /** Adds the spy active player icon in the main action bar. */
    private _spyOnActivePlayerInGeneralActions: boolean = false;

    /** Adds colored <> around the player name in miniboards to show who are the previous and next players. */
    private _showPlayerOrderHints: boolean = false;

    /** Shows a player help card in the player miniboard. */
    private _showPlayerHelp: boolean = false;

    /** Shows a first player icon in the player miniboard */
    private _showFirstPlayer: boolean = false;

    public get showFirstPlayer(): boolean {
        return this._showFirstPlayer;
    }

    public get showPlayerHelp(): boolean {
        return this._showPlayerHelp;
    }

    public get showPlayerOrderHints(): boolean {
        return this._showPlayerOrderHints;
    }

    public get spyOnActivePlayerInGeneralActions(): boolean {
        return this._spyOnActivePlayerInGeneralActions;
    }

    public get spyOnOtherPlayerBoard(): boolean {
        return this._spyOnOtherPlayerBoard;
    }
}
