const {test,expect}=require('@playwright/test');
const path=require('path');

async function addressPage(page,place,geocoderResults=[]){
  await page.setContent(`<form><input data-address-autocomplete><input data-address-place-id><input data-address-latitude><input data-address-longitude><input data-address-part="city"><input data-address-part="state"><input data-address-part="zip"><input data-address-part="country"><input name="shipping_address_2"></form>`);
  await page.evaluate(({place,geocoderResults})=>{
    window.mockPlace={...place,geometry:{location:{lat:()=>place.latitude,lng:()=>place.longitude}}};window.geocoderResults=geocoderResults;window.geocodeRequests=[];
    window.google={maps:{places:{Autocomplete:class{constructor(){window.addressAutocomplete=this;}addListener(name,callback){if(name==='place_changed')this.callback=callback;}getPlace(){return window.mockPlace;}}},Geocoder:class{geocode(request,callback){window.geocodeRequests.push(request);callback(window.geocoderResults,'OK');}}}};
  },{place,geocoderResults});
  await page.addScriptTag({path:path.resolve(__dirname,'../../public/assets/js/googleMaps.js')});
}

test('Google address fallback fills Brazilian CEP by place id',async({page})=>{
  await addressPage(page,{
    place_id:'br-route-without-cep',formatted_address:'Av. Paulista - Bela Vista, São Paulo - SP, Brasil',
    address_components:[{long_name:'São Paulo',types:['locality']},{short_name:'SP',types:['administrative_area_level_1']},{short_name:'BR',types:['country']}],
    latitude:-23.5614,longitude:-46.6559
  },[{address_components:[{long_name:'01310-100',types:['postal_code']}]}]);
  await page.evaluate(()=>window.addressAutocomplete.callback());
  await expect(page.locator('[data-address-part="zip"]')).toHaveValue('01310-100');
  expect(await page.evaluate(()=>window.geocodeRequests[0])).toEqual({placeId:'br-route-without-cep'});
});

test('postal code suffix is assembled correctly regardless of component order',async({page})=>{
  await addressPage(page,{
    place_id:'split-postal-code',formatted_address:'Test address',
    address_components:[{long_name:'350',types:['postal_code_suffix']},{long_name:'08191',types:['postal_code']},{long_name:'São Paulo',types:['locality']},{short_name:'SP',types:['administrative_area_level_1']},{short_name:'BR',types:['country']}],
    latitude:-23.5,longitude:-46.6
  });
  await page.evaluate(()=>window.addressAutocomplete.callback());
  await expect(page.locator('[data-address-part="zip"]')).toHaveValue('08191-350');
  expect(await page.evaluate(()=>window.geocodeRequests.length)).toBe(0);
});
