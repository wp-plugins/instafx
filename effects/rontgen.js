//Effect Name: rontgen
Caman.Filter.register("rontgen", function() {

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
		this.fillColor("#e87b22", 3);
		this.filter.colorize("#3e5632", 10);
		this.filter.invert(1);
		return this ;
	}); 
	
	return this;
});