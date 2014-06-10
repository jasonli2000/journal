// When page ready
var selectIssue = false;
var resizeEvent;

$(document).ready(function(){  
  $('.issueTitle').each(function(){
    $(this).dotdotdot({
    callback	: function( isTruncated, orgContent ) {
      if(isTruncated)
        {
        $(this).qtip({
            content: {
                attr: 'qtipconditionnal'
            }
        });
        }
    }}
    );
 });  

  
  resizeEvent = function(){
    $.each($('.SearchResultEntry'),function(){
      $(this).find('.ResultTitle').dotdotdot( {'height': 20});
      $(this).find('.ResultDescription').trigger("update");
    })

  }
  
  $(window).resize(resizeEvent);

  $('.issuePage').fancybox({type: 'ajax'});
  
  
  // Create the root html element of each tree
  $.each(json.trees, function(key, tree)
    {    
    $('#treeWrapper').append('<div class="TreeEntry"><img class="tooggleButton" src="'+json.global.webroot+'/privateModules/journal/public/images/arrow-bottom.png"/><h4>'+tree.title+' </h4><div id="categoryTree-'+tree.key+'" class="categoryTree"></div>');
    /* Init trees */
    $("div.categoryTree:last").dynatree({
      debugLevel : 0,
      checkbox: true,
      selectMode: 3,
      children: tree.children,
      onSelect: function(select, node) {
        $('#infoElement').hide();
        searchDatabase(false);
      },
      onDblClick: function(node, event) {
        node.toggleExpand();
      },
     
        cookieId: "dynatreeEdit-"+key,
        idPrefix: "dynatreeEdit-"+key
      });
      
    if(tree.children.length > 4)
      {
      $("div.categoryTree:last").hide();
      $("div.categoryTree:last").parent().find('img.tooggleButton').attr('src', json.global.webroot+'/privateModules/journal/public/images/arrow-right.png');
      }
    }
  );  
    
  $('.issueButton .issueTitle, .issueButton .issueSubTitle').click(function(){
    var container = $(this).parents('.issueButton');
    var key = parseInt(container.attr('key'));
    $('.issueSelected').each(function(){
      $(this).removeClass('issueSelected');
      $(this).find('.issueDetails').hide();
    })
    if(selectIssue == key) selectIssue = false;
    else
      {
      container.addClass('issueSelected');
      container.find('.issueDetails').show();
      selectIssue = key;
      }
    $('#infoElement').hide();
    searchDatabase(false);
  });
  if(json.selectedIssue != "")
    {
    selectIssue = parseInt(json.selectedIssue);
    $('.issueButton[key='+selectIssue+']').addClass('issueSelected');
    }

   
  // Init instant search
  $('#live_search').keyup(function(){
      $('#infoElement').hide();
      searchDatabase(false);
    });
      
  // Init tree toogle
  $('img.tooggleButton').click(function(){
    var tree = $(this).parent('div.TreeEntry').find('div.categoryTree');
    if(tree.is(':visible'))
      {
      $(this).attr('src', json.global.webroot+'/privateModules/journal/public/images/arrow-right.png');
      }
    else
      {
      $(this).attr('src', json.global.webroot+'/privateModules/journal/public/images/arrow-bottom.png');
      }
    tree.toggle();
  });
  
  searchDatabase(false)
})

function getSelectedCategories()
  {
  var result = new Array();
  $("#treeWrapper div.categoryTree").each(function(){
    var nodes = $(this).dynatree("getSelectedNodes");
    $.each(nodes, function(index, value){
      result.push(value.data.key);
    });
  });
  return result;
  }

//** Query the api */
function searchDatabase(append)
  {
  var fullQuery = "text-journal.enable:true ";
  var query = $('#live_search').val();
  if(query.indexOf(":") != -1)
    {
    fullQuery += "AND "+query+" ";
    }
  else if(query != "" && query!="Search...")
    {
    fullQuery += "AND (name:"+query+" OR description:"+query+") ";
    }
    
  var categories = getSelectedCategories();
  if(categories.length != 0)
    {
    fullQuery+= " AND (";
    $.each(categories, function(index, value){
      if(index != 0) fullQuery += " AND ";
      if(value.indexOf("certified") != -1)
        {          
        fullQuery+= "text-journal.certification_level:"+value.charAt(value.length - 1)+" ";          
        }
      else
        {
        fullQuery+= " text-journal.categories:"+value+" ";
        }
    });
    fullQuery+= ")";
    }
    
  if(selectIssue)
    {
    fullQuery+= " AND (";
    fullQuery+= " text-journal.issue:"+selectIssue+" ";
    fullQuery+= ")";
    }
  if(json.selectedCommunity != "")
    {
    fullQuery+= " AND (";
    fullQuery+= " text-journal.community:"+json.selectedCommunity+" ";
    fullQuery+= ")";
    }
    
  $('img#searchLoadingImg').show();
  var shown = $('.resourceLink').length;
  if(!append) shown = 0;
  ajaxWebApi.ajax({
        method: 'midas.journal.search',
        args: "offset="+shown+"&query="+fullQuery,
        log: true,
        success: function (retVal) {
          $('img#searchLoadingImg').hide();
          var total = 0;
          if(!append) $('.SearchResults').html("");
          if(!append) $('#noResultElement').show();
          $.each(retVal.data, function(index, value)
          {
          total = value.total;
          $('#noResultElement').hide();
          addAndFormatResult($('.SearchResults'), {'rating': value.rating, 'type': value.type,
            'id':value.revisionId, 'title': value.title, "logo": value.logo,
            'description': value.description, 'statistics': value.statistics,
            'authors': value.authors, 'isCertified' : value.isCertified, 'certifiedLevel': value.certifiedLevel})
          })
          
          var shown = $('.resourceLink').length;
          
          if(total != shown)
            {
            $('#showMoreResults').show();
            $('#showMoreResults a').unbind('click').click(function(){
              searchDatabase(true);
            })
            }
          else
            {
            $('#showMoreResults').hide();
            }
               
          if(total == "")
            {
            $('.SearchCount').hide();
            }
          else if(total > 1)
            {
            $('.SearchCount').show();
            $('.SearchCount').html(total+ " resources available.")  
            }
          else
            {
            $('.SearchCount').show();
            $('.SearchCount').html(total+ " resource available.") 
            }
            
          resizeEvent();
          setTimeout(resizeEvent, 200);
        },
        error: function (retVal) {
            midas.createNotice(retVal.message, 3000, 'error');
        },
        complete: function () {
        }
    });
  }


/** Simple templating mechanism */
function addAndFormatResult(container, values) {
  var str = document.getElementById('SearchResultTemplate').innerHTML;
  $.each(values, function(key,value)
   {
   str = str.replace("{"+key+"}", value);
   str = str.replace("{"+key+"}", value);
   str = str.replace("{"+key+"}", value);
   }
  );
  container.append(str);
  var newElement = $('div.SearchResultEntry:last');
  newElement.find('.ResultTitle').dotdotdot( {'height': 20});
            
  if(values.logo == "")
    {
    newElement.find('.ResultLogo:first').remove();
    }
  else
    {
    newElement.find('.ResultLogo:last').remove();
    }
    
  if(values.isCertified == 0)
    {
    newElement.find('.CertifiedWrapper').remove();
    newElement.find('.CertifiedLevel').remove();
    }
  else
    {
    newElement.find('.CertifiedLevel').html("(Level "+values.certifiedLevel+")");  
    }
    
  newElement.find('.ResultDescription').dotdotdot();
  return str; 
};
