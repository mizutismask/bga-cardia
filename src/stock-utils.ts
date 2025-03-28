const CARD_WIDTH = 165 //also change in scss
const CARD_HEIGHT = 256

function getBackgroundInlineStyleForCardiaCard(destination: CardiaCard) {
	let file
	switch (destination.type) {
		case 1:
			file = 'cardiacards.jpg'
			break
	}

	const imagePosition = destination.type_arg - 1
	const row = Math.floor(imagePosition / IMAGE_ITEMS_PER_ROW)
	const xBackgroundPercent = (imagePosition - row * IMAGE_ITEMS_PER_ROW) * 100
	const yBackgroundPercent = row * 100
	return `background-image: url('${g_gamethemeurl}img/${file}'); background-position: -${xBackgroundPercent}% -${yBackgroundPercent}%; background-size:1000%;`
}

function generateSlotsIds(prefix: string, limit: number) {
	const ids = []
	for (let index = 0; index < limit; index++) {
		ids.push(prefix + (index + 1))
	}
	return ids
}
