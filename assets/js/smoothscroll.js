window.scroller = null;

function init(){
	window.scroller = new SmoothScroll(document,220,24);
}

function SmoothScroll(target, speed, smooth) {
	if (target === document)
		target = (document.scrollingElement 
              || document.documentElement 
              || document.body.parentNode 
              || document.body) // cross browser support for document scrolling
      
	var moving = false
	var pos = target.scrollTop
  var frame = target === document.body 
              && document.documentElement 
              ? document.documentElement 
              : target // safari is the new IE
  
	target.addEventListener('wheel', scrolled, { passive: false });
	target.addEventListener('mousewheel', scrolled, { passive: false });
	target.addEventListener('DOMMouseScroll', scrolled, { passive: false });

	this.setPostion = function(newPosY) {
		pos = newPosY;
		// Si no se está moviendo, inicia el bucle de animación para ir a la nueva posición.
		if (!moving) update();
	};
	this.isMoving = function() { return moving; };
	this.getDestination = function() { return pos; };
	this.stop = function() {
		moving = false;
		pos = target.scrollTop;
	};

	function scrolled(e) {
		e.preventDefault(); // disable default scrolling

		var delta = normalizeWheelDelta(e)

		if (!moving) {
			pos = target.scrollTop;
		}
		pos += -delta * speed
		pos = Math.max(0, Math.min(pos, target.scrollHeight - frame.clientHeight)) // limit scrolling

		if (!moving) update()
	}

	function normalizeWheelDelta(e){
		if (e.type === 'wheel') {
			return e.deltaMode === 1 ? -e.deltaY / 3 : -e.deltaY / 100;
		}
		if(e.detail){
			if(e.wheelDelta)
				return e.wheelDelta/e.detail/40 * (e.detail>0 ? 1 : -1) // Opera
			else
				return -e.detail/3 // Firefox
		}else
			return e.wheelDelta/120 // IE,Safari,Chrome
	}

	function update() {
		moving = true
    
		var delta = (pos - target.scrollTop) / smooth
    
		target.scrollTop += delta
    
		if (Math.abs(delta) > 0.5)
			requestFrame(update)
		else
			moving = false
	}

	var requestFrame = function() { // requestAnimationFrame cross browser
		return (
			window.requestAnimationFrame ||
			window.webkitRequestAnimationFrame ||
			window.mozRequestAnimationFrame ||
			window.oRequestAnimationFrame ||
			window.msRequestAnimationFrame ||
			function(func) {
				window.setTimeout(func, 1000 / 50);
			}
		);
	}()
}