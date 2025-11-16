((Drupal, drupalSettings) => {
  Drupal.behaviors.frontend = {
    attach(context) {
      console.log('test');

      // Randomly generate a thread_id for testing
      const thread_id = Math.random().toString(36).substring(7);

      const evtSource = new EventSource("/ai-react-agent/example?query=dimmi quali content type ci sono sul mio sito&thread_id=" + thread_id);

      // find dom element by id 'block-claro-content'
      const element = document.getElementById("block-claro-content");
      evtSource.onmessage = (event) => {
        if (event.data === "close") {
          evtSource.close();
          return;
        }

        // const newElement = document.createElement("li");
        // newElement.textContent = `message: ${event.data}`;
        element.innerHTML += event.data;
      };

    },
  };
})(Drupal, drupalSettings);
