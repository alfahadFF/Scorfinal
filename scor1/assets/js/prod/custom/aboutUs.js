$(function(){
    "use strict";

    var timelineSlide;
    timelineSlide = $('#timeline .swiper-container').swiper({
        pagination: '#timeline .swiper-pagination',
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
    $('#timeline').find(".swiper-button-next").on("click",function(){
        timelineSlide.slideNext(500);
    });
    $('#timeline').find(".swiper-button-prev").on("click",function(){
        timelineSlide.slidePrev(500);
    });

});