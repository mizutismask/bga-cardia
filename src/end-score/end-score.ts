/**
 * End score board.
 * No notifications.
 */
class ScoreBoard {
	constructor(private game: CardiaGame, private players: CardiaPlayer[]) {
		const headers = document.getElementById('scoretr')
		if (!headers.childElementCount) {
			dojo.place(
				`
                <th></th>
                <th id="th-destination-reached-score" class="">${_('Destinations reached')}</th>
                <th id="th-revealed-tokens-back-score" class="">${_('Revealed destinations reached')}</th>
                <th id="th-destination-unreached-score" class="">${_('Destinations not reached')}</th>
                <th id="th-total-score" class="total-score">${_('Total')}</th>
            `,
				headers
			)
		}

		players.forEach((player) => {
			const playerId = Number(player.id)

			/*dojo.place(
                `<tr id="score${player.id}">
                    <td id="score-name-${player.id}" class="player-name" style="color: #${
                    player.color
                }"><span id="score-winner-${player.id}"/> <span>${player.name}</span></td>
                    <td id="destination-reached${player.id}" class="score-number">${
                    player.completedDestinations.length + player.sharedCompletedDestinationsCount
                }</td>
                    <td id="revealed-tokens-back${player.id}" class="score-number">${
                    player.revealedTokensBackCount
                }</td>
                    <td id="destination-unreached${player.id}" class="score-number">${this.preventMinusZero(
                    player.uncompletedDestinations?.length
                )}</td>
                    <td id="revealed-tokens-left${player.id}" class="score-number">${this.preventMinusZero(
                    player.revealedTokensLeftCount
                )}</td>
                    <td id="total${player.id}" class="score-number total">${player.score}</td>
                </tr>`,
                "score-table-body"
            );*/
		})
	}

	public updateScores(players: CardiaPlayer[]) {
		/*players.forEach((p) => {
            document.getElementById(`destination-reached${p.id}`).innerHTML = (
                p.completedDestinations.length + p.sharedCompletedDestinationsCount
            ).toString();
            document.getElementById(`revealed-tokens-back${p.id}`).innerHTML = p.revealedTokensBackCount.toString();
            document.getElementById(`destination-unreached${p.id}`).innerHTML = this.preventMinusZero(
                p.uncompletedDestinations?.length
            );
            document.getElementById(`revealed-tokens-left${p.id}`).innerHTML = this.preventMinusZero(
                p.revealedTokensLeftCount
            );
            document.getElementById(`total${p.id}`).innerHTML = p.score.toString();
        });*/
	}

	private preventMinusZero(score: number) {
		if (score === 0) {
			return '0'
		}
		return '-' + score.toString()
	}

	public updateScore(playerId: number, scoreType: string, score: number, animate: boolean = true) {
		let elt = dojo.byId(scoreType)
		if (!elt) {
			const playerVariant = `${scoreType}-${playerId}`
			elt = dojo.byId(playerVariant)
			scoreType = playerVariant
		}
		if (!elt) {
			console.error('updateScore : this element can not be displayed', scoreType)
		} else {
			elt.innerHTML = score.toString()
			if (animate) {
				dojo.addClass(scoreType, 'animatedScore')
			}
		}
	}
	/**
	 * Add trophee icon to top score player(s)
	 */
	public highlightWinnerScore(playerId: number | string) {
		document.getElementById(`score${playerId}`).classList.add('highlight')
		document.getElementById(`score-winner-${playerId}`).classList.add('fa', 'fa-trophy', 'fa-lg')
	}
}
