const defaultAnimationDuration = 500
/**
 * This method will attach mobile to a new_parent without destroying, unlike original attachToNewParent which destroys mobile and
 * all its connectors (onClick, etc)
 */
function attachToNewParentNoDestroy(mobileElement: string, newParent: string, relation:string, place_position) {
	log("attaching ",mobileElement,newParent,relation);
	const mobile = $(mobileElement)
	const new_parent = $(newParent)
	const old_parent = mobile.parentNode

	var src = dojo.position(mobile)
	if (place_position) mobile.style.position = place_position
	dojo.place(mobile, new_parent, relation)

	mobile.offsetTop //force re-flow
	var tgt = dojo.position(mobile)
	var box = dojo.marginBox(mobile)
	var cbox = dojo.contentBox(mobile)
	var left = box.l + src.x - tgt.x
	var top = box.t + src.y - tgt.y

	if (place_position != 'relative') mobile.style.position = 'absolute'

	if (old_parent == mobile.parentNode) {
		// parent did not change
	} else {
		mobile.style.left = left + 'px'
		mobile.style.top = top + 'px'
		mobile.offsetTop //force re-flow
	}
	box.l += box.w - cbox.w
	box.t += box.h - cbox.h

	return box
}

/*
 * This method is similar to slideToObject but works on object which do not use inline style positioning. It also attaches object to
 * new parent immediately, so parent is correct during animation
 */
function slideToObjectRelative(token, finalPlace, duration, delay, onEnd, relation) {
	token = $(token)
	delayedExec(
		(duration) => {
			token.style.transition = 'none'
			token.classList.add('moving_token')
			var box = this.attachToNewParentNoDestroy(token, finalPlace, relation, 'static')
			token.offsetHeight // re-flow
			token.style.transition = 'all ' + duration + 'ms ease-in-out'
			token.style.left = box.l + 'px'
			token.style.top = box.t + 'px'
		},
		() => {
			token.style.removeProperty('transition')
			this.stripPosition(token)
			token.classList.remove('moving_token')
			if (onEnd) onEnd(token)
		},
		duration,
		delay
	)
}

function slideToObjectAbsolute(token, finalPlace, x, y, duration, delay, onEnd, relation,position) {
    token = $(token);			
    this.delayedExec(() => {
        token.style.transition = "none";
        token.classList.add('moving_token');
        
        this.attachToNewParentNoDestroy(token, finalPlace, relation, position ? position : 'absolute');
        token.offsetHeight; // re-flow
        token.style.transition = "all " + duration + "ms ease-in-out";
        token.style.left = x + "px";
        token.style.top = y + "px";
    }, () => {
        token.style.removeProperty("transition");
        token.classList.remove('moving_token');
        if (position) token.style.position = position;
        if (onEnd) 
            onEnd(token);
    }, duration, delay);
}

function delayedExec(onStart, onEnd, duration = defaultAnimationDuration, delay: number = 0) {
	if (this.instantaneousMode || this.inSetup) {
		delay = Math.min(1, delay)
		duration = Math.min(1, duration)
	}
	if (delay) {
		setTimeout(function () {
			onStart(duration)
			if (onEnd) {
				setTimeout(onEnd, duration)
			}
		}, delay)
	} else {
		onStart(duration)
		if (onEnd) {
			setTimeout(onEnd, duration)
		}
	}
}

/**
 * This method will remove all inline style added to element that affect positioning
 */
function stripPosition(token) {
	var token = $(token)
	// debug(token + " STRIPPING");
	// remove any added positioning style
	token.style.removeProperty('display')
	token.style.removeProperty('top')
	token.style.removeProperty('left')
	token.style.removeProperty('position')
	token.style.removeProperty('opacity')
}


