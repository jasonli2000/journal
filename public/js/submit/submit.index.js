// When page ready
$(document).ready(function(){
  
  if(json.showlicence == 1)
  {
    $.fancybox.open([
    {
      href : '#licenseWrapper',
      closeBtn:false,
      keys : {
        close  : null
      }
    }]);
  }
   
  $('select[name=disclaimer]').change(function(){
    $('.disclaimer_description').hide();
    $('#disclaimer_description_'+$(this).val()).show();    
  })
   
  $("#authors").dynamiclist();
  $("#tags").dynamiclist();

  $('#submitForm').submit(function(){    
       
    // Convert tree selection to html form
    $('div#treeInputs').html()
    var html = '';
    var i = 0;
    $('.categoryTree').each(function(){
      $.each($(this).dynatree("getSelectedNodes"), function(index, value){
        if(!value.hasSubSel)
        {
          html += "<input name='category["+i+"]' type='hidden' value='"+value.data.key+"'/>";
          i++;
        }
      });
    })
    $('div#treeInputs').html(html);
    return true;
  });
  // Create the root html element of each tree
  $.each(json.trees, function(key, tree)
  {
    tree = FixTreeObjects(tree);
    if(tree.title == "Packages")
      {
      var treeid = "categoryTree-" + tree.key;
      var treeEntry = '<div class="TreeEntry"><b>'+tree.title + '</b>';
      treeEntry += '<span tree="'+treeid+'" id="showPackagesLink">&nbsp;&nbsp;<a>Show Packages</a></span>';
      treeEntry += '<span tree="'+treeid+'" id="hidePackagesLink" style="display:none">&nbsp;&nbsp;';
      treeEntry += '<a>Select all</a>&nbsp; | &nbsp;<a>Select None</a>&nbsp; | &nbsp;<a>Hide Packages</a></span><br/>';
      treeEntry += ' <div style="display:none;" id="categoryTree-'+tree.key+'" class="categoryTree"></div>';
      $('#treeWrapper').append(treeEntry);
      $('#showPackagesLink').click(function(){
         $('#'+treeid).show();
         $('#showPackagesLink').hide();
         $('#hidePackagesLink').show();
      });
      $('#hidePackagesLink a:first').click(function(){
         $('#'+treeid).dynatree("getRoot").visit(function(node){
          node.select(true);
        });
      });
      $('#hidePackagesLink a:eq(1)').click(function(){
         $('#'+treeid).dynatree("getRoot").visit(function(node){
            node.select(false);
         });
      });
      $('#hidePackagesLink a:last').click(function(){
           $('#hidePackagesLink').hide();
           $('#showPackagesLink').show();
           $('#'+treeid).hide();
      });
      }
    else
      {
      $('#treeWrapper').append('<div class="TreeEntry"><b>'+tree.title+' </b><br/><div id="categoryTree-'+tree.key+'" class="categoryTree"></div>');    
      }
    /* Init trees */
    $("div.categoryTree:last").dynatree({
      checkbox: true,
      selectMode: 3,
      children: tree.children,
      cookieId: "dynatreeEdit-"+key,
      idPrefix: "dynatreeEdit-"+key
    });
  });
  
  // Init internal autocomplete search
  $( "#internalResource" ).autocomplete({
    source: function( request, response ) {
      var postRequest = {
        query:"string-portal.enable:true AND name:"+request.term+"*",
        "displayOffset":0, 
        "solrOffset":0, 
        "limit":10
      }

      request.term = "enable:true AND "+request.term;
      var term = request.term;
       
      $("#searchloading").show();
      lastXhr = $.getJSON( $('.webroot').val()+"/solr/advanced/submit", postRequest, function( data, status, xhr ) {
        $("#searchloading").hide();
        if ( xhr === lastXhr ) {
          itemselected = false;
          var items = data.items;
          $.each(items, function(index, value){
            items[index] = {
              "id": value.id, 
              "label": value.name, 
              "value": value.name
              };
          });
          response( items);
        }
      });
    },
    minLength: 2,
    select: function( event, ui ) {
      json.submit.associated.push({
        'item_id': ui.item.id, 
        'name':ui.item.value
      })
      initAssociatedResouces();
    }
  });
  
  
  // New resource creation ( reset form and display it)
  $('a#newResource').click(function(){
    $('div#newResourceForm input[type=text]').val('');
    $('div#newResourceForm').show();
  });
  
  // New external resource
  $("input#saveResource").click(function(){
    json.submit.associated.push({
      'item_id': $('#urlResource').val()+";;"+$('#typeResource').val()+";;"+$('#nameResource').val(), 
      'name': $('#nameResource').val()
      })
    initAssociatedResouces();
    return false;
  })
 

  // Create auto complete tags input
  function split( val ) {
    return val.split( /,\s*/ );
  }
  function extractLast( term ) {
    return split( term ).pop();
  } 
  $( "#tags" )
  // don't navigate away from the field on tab when selecting an item
  .bind( "keydown", function( event ) {
    if ( event.keyCode === $.ui.keyCode.TAB &&
      $( this ).data( "ui-autocomplete" ).menu.active ) {
      event.preventDefault();
    }
  })
  .autocomplete({
    minLength: 0,
    source: function( request, response ) {
      // delegate back to autocomplete, but extract the last term
      response( $.ui.autocomplete.filter(
        json.submit.keywords, extractLast( request.term ) ) );
    },
    focus: function() {
      // prevent value inserted on focus
      return false;
    },
    select: function( event, ui ) {
      var terms = split( this.value );
      // remove the current input
      terms.pop();
      // add the selected item
      terms.push( ui.item.value );
      // add placeholder to get the comma-and-space at the end
      terms.push( "" );
      this.value = terms.join( ", " );
      return false;
    }
  });
  
});
