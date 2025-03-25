/**
 * Player table.
 */
class PlayerTable {
	public handStock: HandStock<CardiaCard>

	constructor(private game: CardiaGame, player: CardiaPlayer) {
		const isMyTable = player.id === game.getPlayerId().toString()
		const ownClass = isMyTable ? 'own' : ''
		let html = `
			<a id="anchor-player-${player.id}"></a>
            <div id="player-table-${player.id}" class="player-order${player.playerNo} player-table ${ownClass}">
				<span class="player-name" style="color:#${player.color}">${player.name}</span>
            </div>
        `
		dojo.place(html, 'player-tables')

		if (isMyTable) {
			const handHtml = `
			<div id="hand-${player.id}" class="nml-player-hand"></div>
        `
			dojo.place(handHtml, `player-table-${player.id}`, 'first')
			this.initHand(player)
		}
	}

	private initHand(player: CardiaPlayer) {
		this.handStock = new HandStock<CardiaCard>(this.game.cardsManager, $('hand-' + player.id), {
			sort: sortFunction('type_arg', '-type'),
			inclination: 8,
			cardShift: '10px',
			cardOverlap: '75px'
		})
		this.handStock.setSelectionMode('single')
	}
}
