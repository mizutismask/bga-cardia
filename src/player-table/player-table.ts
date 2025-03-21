/**
 * Player table.
 */
class PlayerTable {
	private handStock: LineStock<CardiaCard>

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
		const smallWidth = window.matchMedia('(max-width: 830px)').matches
		var baseSettings = {
			center: true,
			gap: '10px'
		}
		if (smallWidth) {
			baseSettings['direction'] = 'row' as 'row'
			baseSettings['wrap'] = 'nowrap' as 'nowrap'
		} else {
			baseSettings['direction'] = 'column' as 'column'
			baseSettings['wrap'] = 'wrap' as 'wrap'
		}

		//log('smallWidth', smallWidth, baseSettings)

		this.handStock = new LineStock<CardiaCard>(this.game.cardsManager, $('hand-' + player.id), baseSettings)
		this.handStock.setSelectionMode('single')
	}
}
