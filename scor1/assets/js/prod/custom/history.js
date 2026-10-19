$(function(){
    "use strict";

    $("#history .item").on("click",function(){
        $.confirm({
            title:false,
            content:"<div><div class='title' style='font-size: 24px;margin:0px 10px 14px'>"+$(this).attr("data-title")+"</div><img style='width:100%' src='"+$(this).find("img").attr("src")+"'/></div>",
            columnClass: 'columns small-10 medium-6 large-6',
            buttons: {
                cancel: {
                    text: $LANG["CLOSE"],
                    btnClass: 'btn-primary',
                    keys: ['esc'],
                    action: function(){

                    }
                }
            }
        });
    })
});