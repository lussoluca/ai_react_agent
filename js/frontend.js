((Drupal, drupalSettings) => {
  Drupal.behaviors.frontend = {
    attach(context) {
      console.log('test');

      // find dom element by id 'block-claro-content'
      const element = document.getElementById("block-claro-content");

      // Create UI elements only once
      if (!document.getElementById('ai-query-input')) {
        // Create container for input elements
        const container = document.createElement('div');
        container.style.marginBottom = '20px';

        // Create textbox
        const textbox = document.createElement('input');
        textbox.type = 'text';
        textbox.id = 'ai-query-input';
        textbox.placeholder = 'Insert your question...';
        textbox.style.width = '600px';
        textbox.style.padding = '8px';
        textbox.style.marginRight = '10px';
        textbox.value = '';

        // Create button
        const button = document.createElement('button');
        button.id = 'ai-query-submit';
        button.textContent = 'Send';
        button.style.padding = '8px 16px';
        button.style.cursor = 'pointer';

        // Create results container
        const resultsDiv = document.createElement('div');
        resultsDiv.id = 'ai-results';
        resultsDiv.style.marginTop = '20px';

        // Add elements to container
        container.appendChild(textbox);
        container.appendChild(button);

        // Insert at the beginning of the element
        element.insertBefore(container, element.firstChild);
        element.insertBefore(resultsDiv, container.nextSibling);

        // Randomly generate a thread_id for testing
        const thread_id = Math.random().toString(36).substring(7);

        // Add click event listener to button
        button.addEventListener('click', () => {
          const query = textbox.value.trim();

          if (!query) {
            alert('Please enter a query.');
            return;
          }

          // Clear previous results
          resultsDiv.innerHTML = '<span style="color: #0a65aa">Thinking...</span><br/>';

          // Encode query for URL
          const encodedQuery = encodeURIComponent(query);

          const evtSource = new EventSource("/ai-react-agent/react?query=" + encodedQuery + "&thread_id=" + thread_id);

          evtSource.onmessage = (event) => {
            resultsDiv.innerHTML += event.data;
          };

          evtSource.addEventListener("close", () => {
            evtSource.close();
          });

          evtSource.addEventListener("tool", (event) => {
            resultsDiv.innerHTML += '<br/><span style="color: #0a65aa">' + event.data + '...</span><br/>';
          });

          evtSource.onerror = (error) => {
            console.error('EventSource error:', error);
            evtSource.close();
            resultsDiv.innerHTML += '<p style="color: red;">Connection error.</p>';
          };
        });

        // Allow pressing Enter to submit
        textbox.addEventListener('keypress', (e) => {
          if (e.key === 'Enter') {
            button.click();
          }
        });
      }
    },
  };
})(Drupal, drupalSettings);
