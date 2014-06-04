$(document).ready(function(){
  
  processQuestionUpdate(true);
  $("div#questionWrapper_"+$("select#listTopics").val()).show();
  $("select#listTopics").change(function(){
    $('div.questionWrapper').hide();
    $("div#questionWrapper_"+$("select#listTopics").val()).show();
  })
  
  $('.selectTopic').click(function(){
    $("select#listTopics").val($(this).attr('value'))
    $("select#listTopics").trigger('change');
  })
  
  if((document.createElement('progress').max === undefined))
    {
    $('progress').parents('tr#summaryProgress').hide();
    }
    
  $('.selectMultipleWrapper select').change(function(){
    var question = $(this).parents('.questionElement').attr('key');
    var topic = $("select#listTopics").val();
    json.listArray.topics[topic].questions[question].value = $(this).val();
    processQuestionUpdate();
  });
  $('.questionElement textarea').change(function(){
    var question = $(this).parents('.questionElement').attr('key');
    var topic = $("select#listTopics").val();
    json.listArray.topics[topic].questions[question].commentValue = $(this).val();
    processQuestionUpdate();
  });
  
  $('#globalComment').change(function(){
    json.listArray.list.comment = $(this).val();
  });
  $('#certificationLevel').change(function(){
    json.listArray.list.certificationLevel = $(this).val();
  });
  
  $('#saveReview').click(function(){
    var url = json.global.webroot+"/reviewosehra/submit?revision_id="+json.listArray.revision_id;
    if(json.listArray.review_id != "")
      {
      url = json.global.webroot+"/reviewosehra/submit?review_id="+json.listArray.review_id;
      }
    $.post(url, {is_complete: percentage, cache_summary: $("#summaryTable").html(), content: JSON.stringify(json.listArray)},function(data)
    {
      window.location.href = data;
    }, 'json')
    
    
  });
});

/** Simple templating mechanism */
function questionTemplate(id, html, values) {
  html = "<div class='questionElement' key='"+id+"' id='questionElement_"+id+"'>"+html+"</div>";
  html = html.replace("{description}", values.description);
  return html; 
};

var percentage;
function processQuestionUpdate(init){
  var isFinalReview = parseInt(json.listArray.list.type) === 2;
  if(init)
    {
    var template = $('div#templateQuestion').html();
    $('#descriptionQuestionList').html(json.listArray.list.description);
    $('#globalComment').val(json.listArray.list.comment);
    $('#certificationLevel').val(json.listArray.list.certificationLevel);
    $.each(json.listArray.topics, function(i, v){      
      // Summary
      var html = "";
      if(i%2 == 0)html += "<tr class='topicSum' id='topicSum_"+i+"'>";
      else html += "<tr class='even' class='topicSum' id='topicSum_"+i+"'>";
      html += "<td><a class='selectTopic' value='"+i+"'>"+v.name+"</a></td>";
      if (isFinalReview){
        html += "<td><input type='text' id='questionLevel_"+i+"' readonly='readonly'/></td>";
      }
      html += "<td><input type='checkbox' readonly='readonly' id='topicComple_"+i+"'/></td>";
      html += "</tr>";
      $('table#summaryTable tbody').append(html);

      // Questions
      $("select#listTopics").append("<option value='"+i+"'>"+v.name+"</options>");
      $("div#divRightPanel h4").after("<div class='questionWrapper' id='questionWrapper_"+i+"'>");
      $.each(v.questions, function(j, q){
      $("div#questionWrapper_"+i).append(questionTemplate(j, template, q));
      });
    });
    }
    
  // Set the questions content
  percentage = 0;
  var totalQuestion = 0;
  var totalQuestionAnswered = 0;
  $.each(json.listArray.topics, function(i, v){  
    var isComplete = v.questions.length != 0;   
    var levelValue = 0;
    $.each(v.questions, function(j, q){
        if (isFinalReview && j===0) levelValue = q.value;
        totalQuestion++;
        if(q.value == 0) isComplete = false;
        if(q.value != 0) totalQuestionAnswered++;
        $('#questionElement_'+j+" select").val(parseInt(q.value));
        $('#questionElement_'+j+" textarea").val(q.commentValue);
      });
    $('#topicComple_'+i).attr('checked', isComplete);
    if (isFinalReview) $('#questionLevel_'+i).val(levelValue);
    });
  percentage = parseInt(100 * totalQuestionAnswered/totalQuestion);
  $('progress').attr("value", percentage);
  }
