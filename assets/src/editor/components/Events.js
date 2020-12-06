import React from 'react';

const Event = () => {
	function createMarkup() {
		return {__html: '<div id="gp-upcoming-events-container"> <div class="bg-white"> <div class="flex p-4"> <div class="flex-shrink-0 w-1/3 self-start mr-4"> <img width="300" height="200" src="http://gatherpress.test/wp-content/uploads/2020/04/11536523_10153335851424020_7949477911082410985_o-300x200.jpg" class="attachment-medium size-medium wp-post-image" alt="" loading="lazy" srcset="http://gatherpress.test/wp-content/uploads/2020/04/11536523_10153335851424020_7949477911082410985_o-300x200.jpg 300w, http://gatherpress.test/wp-content/uploads/2020/04/11536523_10153335851424020_7949477911082410985_o-1024x683.jpg 1024w, http://gatherpress.test/wp-content/uploads/2020/04/11536523_10153335851424020_7949477911082410985_o-768x512.jpg 768w, http://gatherpress.test/wp-content/uploads/2020/04/11536523_10153335851424020_7949477911082410985_o-1536x1024.jpg 1536w, http://gatherpress.test/wp-content/uploads/2020/04/11536523_10153335851424020_7949477911082410985_o.jpg 1800w" sizes="(max-width: 300px) 100vw, 300px" style="width:100%;height:66.67%;max-width:1800px;" /> </div> <div class="flex-grow w-2/3"> <h5> Thu, December 24, 5:00pm EST </h5> <h3> <a href="http://gatherpress.test/events/a-web-for-everyone-online-835/" class="block"> A Web for Everyone (Online) </a> </h3> <p> <p>By expanding the way we think about users, we are able to design, build, and write for more users. This will be an Introduction to Accessibility where we will go through what you can be doing differently on your website to help make the internet more useable for everyone.</p> </p> </div> </div> </div> </div>'};
	}

	return <div dangerouslySetInnerHTML={createMarkup()} />;
};

export default Event;
