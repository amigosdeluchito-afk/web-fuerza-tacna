var cursor = document.querySelectorAll(".cursor"),
    follower = document.querySelectorAll(".cursor-follower");

var posX = 0,
    posY = 0;

var mouseX = 0,
    mouseY = 0;

// Aseguramos que su posición base sea 0,0 para que las transformaciones por GPU (x, y) sean exactas
TweenMax.set(cursor, { left: 0, top: 0 });
TweenMax.set(follower, { left: 0, top: 0 });

function renderCursor() {
    posX += (mouseX - posX) / 9;
    posY += (mouseY - posY) / 9;
    
    if (follower.length > 0) {
        TweenMax.set(follower, {
            x: posX - 12,
            y: posY - 12,
            force3D: true
        });
    }
    
    if (cursor.length > 0) { 
        TweenMax.set(cursor, {
            x: mouseX,
            y: mouseY,
            force3D: true
        });
    }
    requestAnimationFrame(renderCursor);
}
requestAnimationFrame(renderCursor);

document.addEventListener("mousemove", function(e) {
    mouseX = e.clientX;
    mouseY = e.clientY;
});

document.addEventListener("mouseover", function(e) {
    if (e.target.closest && e.target.closest(".link")) {
        cursor.forEach(function(c) { c.classList.add("active"); });
        follower.forEach(function(f) { f.classList.add("active"); });
    }
});

document.addEventListener("mouseout", function(e) {
    if (e.target.closest && e.target.closest(".link")) {
        cursor.forEach(function(c) { c.classList.remove("active"); });
        follower.forEach(function(f) { f.classList.remove("active"); });
    }
});
