$(function(){
    "use strict";
    $(".cover").imgLiquid();
    document.querySelector("#slide .scrollIcon").addEventListener("click", function () {
        const targetElement = document.querySelector("#threeD");
        const offset = 90; // İstenilen offset miktarı

        // Hedefe kaydırırken offset uygula
        window.scrollTo({
            top: targetElement.offsetTop - offset,
            behavior: "smooth" // Yumuşak kaydırma
        });
    });


    if (navigator.userAgent.toLowerCase().indexOf('iphone') >= 0) {
        $("video").each(function(){
            var id = $(this).attr("data-id");
            if(typeof id != "undefined"){
                var video = new CanvasVideoPlayer({
                    videoSelector: '#'+id+' video',
                    canvasSelector: '#'+id+' canvas',
                    framesPerSecond: 60,
                    hideVideo: true,
                    resetOnLastFrame: true // should video reset back to the first frame after it finishes playing
                });

                setInterval(function(){
                    video.play();
                },6000);
            }
        });
    }

    var lng = $SITE.lang.includes('-') ? $SITE.lang.split('-')[1] : $SITE.lang;

    var texts = []
    switch(lng.toUpperCase()){
        case "TR":
            texts = ["GÜCÜN","YETENEĞİN","PERFORMANSIN","TEKNOLOJİNİN"];
            break;
        case "ES":
            texts = ["DEL PODER","DE LA CAPACIDAD","DEL RENDIMIENTO","DE LA TECNOLOGÍA"];
            break;
        case "DE":
            texts = ["KRAFT","FÄHİGKEIT","LEISTUNG","TECHNOLOGIE"];
            break;
        case "FR":
            texts = ["DE LA PUISSANCE","DE CAPACITÉ","DE PERFORMANCE","DE LA TECHNOLOGIE"];
            break;
        case "RU":
            texts = ["МОЩНОСТИ","ВОЗМОЖНОСТЕЙ","ПРОИЗВОДИТЕЛЬНОСТИ","ТЕХНОЛОГИЙ"];
            break;
        default:
            texts = ["POWER","ABILITY","PERFORMANCE","TECHNOLOGY"];
            break;
    }

    var i = 0;
    setInterval(function(){
        i = (i >= texts.length?0:i+1);
        $("#slide .bigText span").html(texts[i]);
    },1500);

    var mainslide;
    var slideIndex = 0;
    mainslide = $('#slide .swiper-container').swiper({
        pagination: '#slide .swiper-pagination',
        paginationClickable:true,
        keyboardControl:true,
        slidesPerView: 1,
        loop:false,
        autoplay:5000,
        speed:600,
        grabCursor:true,
        parallax:false
    });
    $('#slide .swiper-container').find(".swiper-button-next").on("click",function(){
        mainslide.slideNext(500);
    });
    $('#slide .swiper-container').find(".swiper-button-prev").on("click",function(){
        mainslide.slidePrev(500);
    });

    var newsSlide;
    newsSlide = $('#news .swiper-container').swiper({
        pagination: '#categories .swiper-pagination',
        paginationClickable:true,
        keyboardControl:true,
        slidesPerView: "auto",
        loop:true,
        autoplay:false,
        speed:2000,
        grabCursor:true,
        parallax:true,
        onTransitionStart:function(swiper){
        }
    });
    $('#news').find(".swiper-button-next").on("click",function(){
        newsSlide.slideNext(500);
    });
    $('#news').find(".swiper-button-prev").on("click",function(){
        newsSlide.slidePrev(500);
    });


    var eventsSlide;
    eventsSlide = $('#events .swiper-container').swiper({
        pagination: '#events .swiper-pagination',
        paginationClickable:true,
        keyboardControl:true,
        slidesPerView: "auto",
        loop:false,
        autoplay:false,
        speed:2000,
        grabCursor:true,
        parallax:true,
        onTransitionStart:function(swiper){
        }
    });
    $("#turnIcon").mousedown(function(){
        $(this).children("img").fadeOut("fast")
        return false;
    })


    setTimeout(function(){
        var ctrl = new ScrollMagic.Controller();

        $(".animTrigger").each(function(i) {
            var inner = $(this).find(".anim");
            var tl = new TimelineMax();

            tl.staggerFrom(inner, 0.5, { delay: 0.1, opacity:0, ease:Power4.easeOut}, 0.2);

            var offset = (typeof $(this).attr("data-offset") != "undefined"?$(this).attr("data-offset"):203);

            new ScrollMagic.Scene({
                triggerElement: this,triggerHook: 'onEnter', offset: offset
            })
                .setTween(tl)
                // .addIndicators({
                //     colorTrigger: "#555",
                //     colorStart: "#555",
                //     colorEnd: "#555",
                //     indent: 40
                // })
                .addTo(ctrl);
        });
    },300);

    setTimeout(function(){
        $("#slide").addClass('active');
    },1000)


    var home360id = $("#threeD").attr("data-id");
    const threesixty = new ThreeSixty(document.getElementById('threeDViewPort'), {
        image: [
            '/assets/img/products/'+home360id+'/360/1.jpg?2',
            '/assets/img/products/'+home360id+'/360/2.jpg?2',
            '/assets/img/products/'+home360id+'/360/3.jpg?2',
            '/assets/img/products/'+home360id+'/360/4.jpg?2',
            '/assets/img/products/'+home360id+'/360/5.jpg?2',
            '/assets/img/products/'+home360id+'/360/6.jpg?2',
            '/assets/img/products/'+home360id+'/360/7.jpg?2',
            '/assets/img/products/'+home360id+'/360/8.jpg?2',
            '/assets/img/products/'+home360id+'/360/9.jpg?2',
            '/assets/img/products/'+home360id+'/360/10.jpg?2',
            '/assets/img/products/'+home360id+'/360/11.jpg?2',
            '/assets/img/products/'+home360id+'/360/12.jpg?2',
            '/assets/img/products/'+home360id+'/360/13.jpg?2',
            '/assets/img/products/'+home360id+'/360/14.jpg?2',
            '/assets/img/products/'+home360id+'/360/15.jpg?2',
            '/assets/img/products/'+home360id+'/360/16.jpg?2',
            '/assets/img/products/'+home360id+'/360/17.jpg?2',
        ],
        keys: true,         // Rotate image on arrow keys. Default: true
        draggable: true,    // Rotate image by dragging. Default: true
        swipeable: true,    // Rotate image by swiping on mobile screens. Default: true
        dragTolerance: 10,  // Rotation speed when dragging. Default: 10
        swipeTolerance: 10, // Rotation speed when swiping. Default: 10
        // Rotation settings
        speed: 1000,     // Rotation speed during 'play' mode. Default: 10
        inverted: false, // Inverts rotation direction
        swipeTarget: document.getElementById('turnIcon'),
        prev: document.getElementById('prev'),
        next: document.getElementById('next')
    });
    threesixty.goto(2);

});