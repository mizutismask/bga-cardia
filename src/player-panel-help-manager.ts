interface PlayerPanelHelpSettings {
	/**
	 * The list of buttons to show on the current player panel.
	 */
	playerPanelButtons: BgaHelpButton[]
	destination: string
}

class PlayerPanelHelpManager extends HelpManager {
	constructor(game: Game, settings: HelpSettings, playerPanelSettings: PlayerPanelHelpSettings) {
		super(game, settings)
		if (!playerPanelSettings?.playerPanelButtons) {
			throw new Error('PlayerPanelHelpManager need a `playerPanelButtons` list in the settings.')
		}
		if (!playerPanelSettings?.destination) {
			throw new Error('PlayerPanelHelpManager need a `destination` in the settings.')
		}
		if ($(playerPanelSettings?.destination)) {
			const buttons = document.createElement('div')
			buttons.id = `bga-player-panel-help_buttons`
			$(playerPanelSettings?.destination).appendChild(buttons)
			settings.buttons.forEach((button) => button.add(buttons))
		}
	}
}
