//Effect Name: blues
Caman.Filter.register("blues", function() {

	this.saturation(-20);
	this.gamma(1.1);
	this.channels({
		red: -10,
		green: 2,
		blue: 5
	});
	this.curves('rgb', [0, 0], [80, 50], [128, 230], [255, 255]);
	this.newLayer(function(){
		this.setBlendingMode('exclusion');
		this.filter.invert(1);
		return this ;
	}); 
	
	return this;
});