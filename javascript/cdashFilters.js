function addFilter(indexSelected)
{
  string=$(".filterFields:last").html();// find index last element
  index=$(".filterFields:last input:last").attr("name");
  index=parseInt(index.substr(3));
  // create html
  string=string.replace("name=\"field"+index,"name=\"field"+(index+1));
  string=string.replace("id=\"id_compare"+index+"\" name=\"compare"+index+"\"","id=\"id_compare"+(index+1)+"\" name=\"compare"+(index+1)+"\"");
  string=string.replace("id=\"id_value"+index+"\" name=\"value"+index+"\"","id=\"id_value"+(index+1)+"\" name=\"value"+(index+1)+"\"");
  string=string.replace("name=\"remove"+index,"name=\"remove"+(index+1));
  string=string.replace("name=\"add"+index,"name=\"add"+(index+1));
  string=string.replace("disabled=\"disabled\"","");
  string=string.replace("removeFilter("+index+")","removeFilter("+(index+1)+")");
  class_filed='treven';
  if($(".filterFields:last").attr("class")=="treven filterFields")
    {
    class_filed='trodd';
    }
  $(".filterFields:last").after('<tr class="'+class_filed+' filterFields">'+string+'</tr>'); //create new element
  $(".filterFields:last input[type='text']").attr("value","");
  $(".filterFields:first input[value='-']").attr("disabled",false); //enable remove button
  $("input[name='filtercount']").attr("value",countFilters()); //set value of the hidden input which tell the number of filter
}

function removeFilter(index)
{
  $(".filterFields").each(function(){ // remove selected element
    if($(this).find("input:last").attr("name")=="add"+index)
      {
      $(this).remove();
      }
  });
  if(countFilters()==1)
    {
    $(".filterFields:first input[value='-']").attr("disabled",true);
    }
  $("input[name='filtercount']").attr("value",countFilters());
}

function clearFilter()
{
  $(".filterFields:gt(0)").remove();// keep only one element(this element will be empty)
  $(".filterFields input[type='text']").attr("value","");
  $(".filterFields input[value='-']").attr("disabled",true);
  $("input[name='filtercount']").attr("value",1);
}

function countFilters()
{
  i=0;
  $(".filterFields").each(function(){
    i++;
  });
  return i;
}

function filters_toggle()
{
  if ($("#label_showfilters").html() == "[Hide Filters]")
    {
    $("#div_showfilters").hide();
    $("#label_showfilters").html("[Show Filters]");
    return;
    }

  $("#div_showfilters").show();
  $("#label_showfilters").html("[Hide Filters]");
}


function set_bool_compare_options(s)
{
  s.options.length = 0;
  s.options[0] = new Option("-- choose comparsion --", "0", true, true);
  s.options[1] = new Option("is true", "1", false, false);
  s.options[2] = new Option("is false", "2", false, false);
}


function set_number_compare_options(s)
{
  s.options.length = 0;
  s.options[0] = new Option("-- choose comparsion --", "40", true, true);
  s.options[1] = new Option("is", "41", false, false);
  s.options[2] = new Option("is not", "42", false, false);
  s.options[3] = new Option("is greater than", "43", false, false);
  s.options[4] = new Option("is less than", "44", false, false);
}


function set_string_compare_options(s)
{
  s.options.length = 0;
  s.options[0] = new Option("-- choose comparsion --", "60", true, true);
  s.options[1] = new Option("contains", "63", false, false);
  s.options[2] = new Option("does not contain", "64", false, false);
  s.options[3] = new Option("is", "61", false, false);
  s.options[4] = new Option("is not", "62", false, false);
  s.options[5] = new Option("starts with", "65", false, false);
  s.options[6] = new Option("ends with", "66", false, false);
}


function set_date_compare_options(s)
{
  s.options.length = 0;
  s.options[0] = new Option("-- choose comparsion --", "80", true, true);
  s.options[1] = new Option("is", "81", false, false);
  s.options[2] = new Option("is not", "82", false, false);
  s.options[3] = new Option("is after", "83", false, false);
  s.options[4] = new Option("is before", "84", false, false);
}


// Prefix cdf_ == cdashFilters variable...
//
var cdf_last_data_type_value = '';


// See http://wsabstract.com/javatutors/selectcontent.shtml for an article
// on how to changs SELECT element content on the fly from javascript...
//
// And see http://www.throbs.net/web/articles/IE-SELECT-bugs/#ieInnerHTMLproperty
// and http://support.microsoft.com/default.aspx?scid=kb;en-us;276228 for articles
// explaining why you cannot use the select element's innerHTML to do it.
//
function update_compare_options(o)
{
  // Get current "name/type" value from o.value / o.options[o.selectedIndex].value:
  //
  cmps = o.value.split('/');
  name = cmps[0];
  type = cmps[1];

  // Only change the comparison choices if changing the data type
  // of the selected field...
  //
  if (type != cdf_last_data_type_value)
    {
    switch (type)
      {
      case 'bool':
      case 'date':
      case 'number':
      case 'string':
        opts = 'valid data type';
        break;
      default:
        opts = '';
        alert('error: unknown data type in javascript update_compare_options. type="' + type + '"');
        break;
      }

    if (opts != '')
      {
      cdf_last_data_type_value = type;

      // o.name is like "field1": Get just the trailing number from o.name:
      //
      num = o.name.substr(5);

      // o.name is "field1" (through "fieldN") -- when its selectedIndex changes,
      // we need to update the list in "id_compare1" to reflect the comparison
      // choices available for the type of data in field1...
      //
      selectElement = document.getElementById('id_compare' + num);

      switch (type)
        {
        case 'bool':
          set_bool_compare_options(selectElement);
          break;

        case 'date':
          set_date_compare_options(selectElement);
          break;

        case 'number':
          set_number_compare_options(selectElement);
          break;

        case 'string':
          set_string_compare_options(selectElement);
          break;

        // Should never get here because of above logic, but:
        //
        default:
          alert('error: unknown data type in javascript update_compare_options. type="' + type + '"');
          break;
        }

      // Also clear the corresponding 'value' input when data type changes:
      //
      valueElement = document.getElementById('id_value' + num);
      valueElement.value = '';
      }
    }
}


//alert(o.name
//  + ' ' + compareSelect.name
//  + " num='" + num + "'"
//  + " name='" + name + "'"
//  + " type='" + type + "'"
//  + " selectedIndex=" + o.selectedIndex
//  + " value=" + o.value
//  + " (" + o.options[o.selectedIndex].value + ")"
//  + " (" + o.options[o.selectedIndex].innerHTML + ")"
//  );


function filters_field_changed(o)
{
  update_compare_options(o);
}