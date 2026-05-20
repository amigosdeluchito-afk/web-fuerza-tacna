var cursor = $(".cursor"),
    follower = $(".cursor-follower");

var posX = 0,
    posY = 0;

var mouseX = 0,
    mouseY = 0;

// Aseguramos que su posición base sea 0,0 para que las transformaciones por GPU (x, y) sean exactas
TweenMax.set(cursor, { left: 0, top: 0 });
TweenMax.set(follower, { left: 0, top: 0 });

TweenMax.to({}, 0.016, {
  repeat: -1,
  onRepeat: function() {
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
  }
});

$(document).on("mousemove", function(e) {
    mouseX = e.clientX;
    mouseY = e.clientY;
});

$(".link").on("mouseenter", function() {
    cursor.addClass("active");
    follower.addClass("active");
});
$(".link").on("mouseleave", function() {
    cursor.removeClass("active");
    follower.removeClass("active");
});
