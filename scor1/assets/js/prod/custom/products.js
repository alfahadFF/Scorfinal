/*
 * "Request Offer" on the product sidebar scrolls to the contact form (handled in app.min.js)
 * and pre-fills the subject selects plus the product the visitor is interested in.
 * The consent checkbox is deliberately left untouched: a pre-ticked consent box is not a
 * valid explicit consent under KVKK art. 3/1-a and GDPR art. 4(11) / recital 32.
 */
$(function(){
    "use strict";

    var SUBJECT_TYPE_INFORMATION_REQUEST = "2";
    var SUBJECT_CATEGORY_SALES = "2";

    $(".requestOffer").on("click", function(){
        var form = $("#contactForm");
        if(form.length === 0){
            return;
        }

        form.find("input[name='product_id']").val($(this).attr("data-product-id") || "");

        $("#input_subject_type").val(SUBJECT_TYPE_INFORMATION_REQUEST).trigger("change");
        $("#input_subject_category").removeAttr("disabled").val(SUBJECT_CATEGORY_SALES).trigger("change");
    });
});

$(function(){
    "use strict";

    if(!isMobile()){
        $("#sideBar").stick_in_parent({offset_top:160});
        $("#product ul li .header").stick_in_parent({offset_top:120});
        $(".specGroups h4").stick_in_parent({offset_top:190});
        $("#stickyFilter").stick_in_parent({
            offset_top: 107
        });


// Sticky elementin boyut değişikliklerini algılamak için ResizeObserver kullanımı
        if (window.ResizeObserver) {
            const observer = new ResizeObserver(() => {
                $("#stickyFilter").trigger("sticky_kit:detach"); // Mevcut sticky ayarını kaldır
                $("#stickyFilter").stick_in_parent({ offset_top: 107 }); // Yeniden başlat
            });

            const stickyElement = document.querySelector("#stickyFilter");
            if (stickyElement) {
                observer.observe(stickyElement);
            }
        } else {
            // Eğer ResizeObserver desteklenmiyorsa alternatif yöntem
            setInterval(() => {
                $("#stickyFilter").trigger("sticky_kit:detach");
                $("#stickyFilter").stick_in_parent({ offset_top: 107 });
            }, 1000); // 1 saniyede bir yeniden hesapla
        }
    }

    $("#stickyFilter form select").on("change",function(){
        var form = $(this).parents("form");
        form.submit();
    });
    $("#stickyFilter .showFilter").on("click",function(){
        $("#stickyFilter .mainFilter").toggleClass("active");
        return false;
    });

    $("#sideBar .buttons .button.download").on("click",function(){
        $(this).toggleClass("active");
        var status = ($(this).hasClass("active")?true:false);
        $(this).find("span").attr("class",(!status?"icon-plus":"icon-minus"));
    });

    $("#product ul li .header .action .off").on("click",function(){
        $(this).parents("li.tab").removeClass("active").children(".content").slideUp("fast");
    });
    $("#product ul li .header .action .on").on("click",function(){
        $(this).parents("li.tab").addClass("active").children(".content").slideDown("fast");
    });

    $(".referenceSlide").each(function(i){
        $(this).addClass("s_"+i);
        $(this).swiper({
            pagination: '.'+"s_"+i+' .swiper-pagination',
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
    });


    if($("#threeDViewPort").length > 0){
        var id = $("#threeDViewPort").attr("data-id");

        const threesixty = new ThreeSixty(document.getElementById('threeDViewPort'), {
            image: [
                '/assets/img/products/'+id+'/360/1.jpg',
                '/assets/img/products/'+id+'/360/2.jpg',
                '/assets/img/products/'+id+'/360/3.jpg',
                '/assets/img/products/'+id+'/360/4.jpg',
                '/assets/img/products/'+id+'/360/5.jpg',
                '/assets/img/products/'+id+'/360/6.jpg',
                '/assets/img/products/'+id+'/360/7.jpg',
                '/assets/img/products/'+id+'/360/8.jpg',
                '/assets/img/products/'+id+'/360/9.jpg',
                '/assets/img/products/'+id+'/360/10.jpg',
                '/assets/img/products/'+id+'/360/11.jpg',
                '/assets/img/products/'+id+'/360/12.jpg',
                '/assets/img/products/'+id+'/360/13.jpg',
                '/assets/img/products/'+id+'/360/14.jpg',
                '/assets/img/products/'+id+'/360/15.jpg',
                '/assets/img/products/'+id+'/360/16.jpg',
                '/assets/img/products/'+id+'/360/17.jpg',
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
        $("#turnIcon").mousedown(function(){
            $(this).children("img").fadeOut("fast")
            return false;
        })
    }
});
